<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Availability;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    // ==========================================
    // 1. PAGINA WEERGAVES (VIEWS)
    // ==========================================

    public function index() {
        $users = User::with('schedules')->get();

        $users->map(function($user) {
            $totalHours = 0;
            // Schatting gemiddelde duur per shift
            $avgShiftDuration = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;

            foreach($user->schedules as $schedule) {
                // We tellen de uren (Day = 9, AM/PM = gemiddelde)
                if ($schedule->shift_type === 'DAY') {
                    $totalHours += 9;
                } else {
                    $totalHours += $avgShiftDuration;
                }
            }
            
            $user->planned_hours_total = round($totalHours, 1);
            return $user;
        });

        return view('dashboard', ['stats' => $users]);
    }

    public function team() {
        $users = User::all();
        return view('team', compact('users'));
    }

    public function admin() {
        return view('admin');
    }

    // ==========================================
    // 2. API (VOOR DE KALENDER)
    // ==========================================

    public function getEvents() {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->select('schedules.date', 'schedules.shift_type', 'users.name', 'users.contract_hours', 'users.contract_days')
            ->get();

        $events = $schedules->map(function($row) {
            if ($row->shift_type == 'AM') $color = '#0070d2'; 
            elseif ($row->shift_type == 'PM') $color = '#04844b'; 
            else $color = '#d68100'; 

            $times = $this->calculateShiftTimes($row->date, $row->shift_type, $row->contract_hours, $row->contract_days);

            $typeLabel = match($row->shift_type) {
                'AM' => '(Ochtend)',
                'PM' => '(Middag)',
                'DAY' => '(Dag)',
                default => ''
            };

            return [
                'title' => $row->name . ' ' . $typeLabel,
                'start' => $times['start'],
                'end'   => $times['end'],
                'color' => $color
            ];
        });

        return response()->json($events);
    }

    private function calculateShiftTimes($dateStr, $shiftType, $contractHours, $contractDays) {
        $date = Carbon::parse($dateStr);
        // Gemiddelde duur (bijv 32u / 4d = 8u)
        $avgDuration = ($contractDays > 0) ? ($contractHours / $contractDays) : 8;

        $startTime = '00:00';

        if ($shiftType === 'DAY') {
            $startTime = '09:00';
            $avgDuration = 9; // Dagdienst is vast 9 uur
        } elseif ($shiftType === 'PM') {
            $startTime = '14:00';
        } else {
            // AM Shift
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                $startTime = '06:00';
            } else {
                $startTime = '05:00';
            }
        }

        $start = Carbon::parse("$dateStr $startTime:00");
        $end = $start->copy()->addMinutes($avgDuration * 60);

        return [
            'start' => $start->toIso8601String(),
            'end'   => $end->toIso8601String()
        ];
    }

    // ==========================================
    // 3. ACTIES (OPSLAAN)
    // ==========================================

    public function storeUser(Request $request) {
        $request->validate([
            'name' => 'required', 
            'email' => 'required|email|unique:users,email',
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array'
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days,
            'password' => bcrypt('welkom123')
        ]);

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach($days as $day) {
            DB::table('availabilities')->insert([
                'user_id' => $user->id,
                'day_of_week' => $day,
                'shift_preference' => $request->shift_preference,
                'created_at' => now(), 'updated_at' => now()
            ]);
        }

        return redirect('/nieuwegein/team')->with('success', 'Collega ' . $user->name . ' toegevoegd!');
    }

    // ==========================================
    // 4. GENERATE LOGICA (ALGORITME)
    // ==========================================

    public function generateSchedule(Request $request)
    {
        // 1. Weekrange instellen (Start altijd op Zaterdag)
        $startDate = Carbon::parse($request->start_date);
        if ($startDate->dayOfWeek !== Carbon::SATURDAY) {
            $startDate->previous(Carbon::SATURDAY); 
        }
        $weekStart = $startDate->copy();
        $weekEnd   = $startDate->copy()->addDays(6);

        // ---------------------------------------------------------
        // FASE 1: Eerst ALLE vaste dagen inplannen voor de hele week
        // ---------------------------------------------------------
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->format('l');

            if ($dayName === 'Thursday' || $dayName === 'Friday') {
                $shifts = ['DAY'];
            } else {
                $shifts = ['AM', 'PM'];
            }
            
            $this->scheduleFixedUsers($currentDate, $shifts, $weekStart, $weekEnd);
        }

        // ---------------------------------------------------------
        // FASE 2: Gaten opvullen (Za t/m Wo)
        // ---------------------------------------------------------
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->format('l');

            // Sla donderdag en vrijdag over voor opvulling
            if ($dayName === 'Thursday' || $dayName === 'Friday') {
                continue;
            }

            foreach (['AM', 'PM'] as $shiftType) {
                $this->fillShiftGaps($currentDate, $shiftType, $weekStart, $weekEnd);
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster gegenereerd: Prioriteit op vullen (max 40u)!');
    }

    /**
     * FASE 1: Vaste medewerkers inplannen.
     */
    private function scheduleFixedUsers($date, $availableShifts, $weekStart, $weekEnd)
    {
        $dayName = $date->format('l');
        $fixedUsers = User::whereJsonContains('fixed_days', $dayName)->get();

        foreach ($fixedUsers as $user) {
            if ($this->userHasShiftToday($user->id, $date)) continue;

            $targetShift = null;

            if (in_array('DAY', $availableShifts)) {
                $targetShift = 'DAY';
            } else {
                // Bepaal AM/PM obv voorkeur
                $pref = Availability::where('user_id', $user->id)
                    ->where('day_of_week', $dayName)
                    ->value('shift_preference') ?? 'BOTH';

                if ($pref === 'AM') $targetShift = 'AM';
                elseif ($pref === 'PM') $targetShift = 'PM';
                else {
                    // Bij BOTH: Check rusttijd achteruit
                    if ($this->workedLateYesterday($user->id, $date)) {
                        $targetShift = 'PM';
                    } else {
                        $targetShift = 'AM';
                    }
                }
            }

            // Plan in (isFixed=true: forceer ook als uren licht overschreden worden)
            if ($this->canWorkShift($user, $date, $targetShift, $weekStart, $weekEnd, true)) {
                $this->assignShift($date, $targetShift, $user->id);
            }
        }
    }

    /**
     * FASE 2: Gaten vullen (Prioriteit: Contract halen -> Overuren maken).
     */
    private function fillShiftGaps($date, $shiftType, $weekStart, $weekEnd)
    {
        $dayName = $date->format('l');

        // DOEL: Maandagochtend 1 persoon, anders 2
        $targetPerShift = 2; 
        if ($dayName === 'Monday' && $shiftType === 'AM') {
            $targetPerShift = 1;
        }

        // Check huidige bezetting
        $currentCount = DB::table('schedules')
            ->where('date', $date->format('Y-m-d'))
            ->where('shift_type', $shiftType)
            ->count();

        $needed = $targetPerShift - $currentCount;
        if ($needed <= 0) return;

        // 1. Haal kandidaten op
        $candidates = User::whereHas('availability', function($q) use ($dayName, $shiftType) {
            $q->where('day_of_week', $dayName);
            if ($shiftType !== 'DAY') {
                $q->whereIn('shift_preference', [$shiftType, 'BOTH']);
            }
        })->get();

        // 2. BEREKEN PRIORITEIT
        $candidates = $candidates->map(function($user) use ($weekStart, $weekEnd) {
            $planned = $this->calculateWeeklyHours($user, $weekStart, $weekEnd);
            // Uren die nog "moeten" volgens contract
            $user->hours_remaining = $user->contract_hours - $planned;
            return $user;
        });

        // 3. Sorteer: Mensen met meeste 'te goed' uren bovenaan
        // Mensen met overuren (negatieve remaining) komen onderaan, maar worden WEL meegenomen.
        $candidates = $candidates->sortByDesc('hours_remaining');

        // 4. Vul de gaten
        foreach ($candidates as $candidate) {
            if ($needed <= 0) break;

            // We hebben de check 'hours_remaining <= 0' verwijderd.
            // Iedereen mag werken, zolang canWorkShift (40u limiet) het toestaat.

            if ($this->canWorkShift($candidate, $date, $shiftType, $weekStart, $weekEnd)) {
                $this->assignShift($date, $shiftType, $candidate->id);
                $needed--;
            }
        }
    }

    /**
     * Centrale controle: Mag deze persoon werken?
     * Nu met max 40 uur limiet (i.p.v. strikt contract).
     */
    private function canWorkShift($user, $date, $shiftType, $weekStart, $weekEnd, $isFixed = false)
    {
        // 1. Dubbele shift check
        if ($this->userHasShiftToday($user->id, $date)) return false; 

        // 2. RUSTTIJDEN
        // A. Achteruit: Gisteren PM -> Vandaag AM mag NIET.
        if ($shiftType === 'AM' && $this->workedLateYesterday($user->id, $date)) {
            return false;
        }
        // B. Vooruit: Vandaag PM -> Morgen AM mag NIET.
        if ($shiftType === 'PM' && $this->worksEarlyTomorrow($user->id, $date)) {
            return false;
        }

        // 3. Contract & Limieten
        if (!$isFixed) {
            $shiftsThisWeek = DB::table('schedules')
                ->where('user_id', $user->id)
                ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
                ->count(); 

            $plannedHours = $this->calculateWeeklyHours($user, $weekStart, $weekEnd);
            $newShiftHours = ($shiftType === 'DAY') ? 9 : (($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0);

            // AANPASSING: Maximaal 6 dagen werken per week als overwerk
            if ($shiftsThisWeek >= 6) return false;

            // AANPASSING: Maximaal 40 uur per week (Harde limiet voor iedereen)
            // Dus ook als je contract 24 uur is, mag je tot 40 uur werken om gaten te vullen.
            if (($plannedHours + $newShiftHours) > 40) return false;
        }

        return true;
    }

    // ==========================================
    // HELPER FUNCTIES
    // ==========================================

    private function calculateWeeklyHours($user, $weekStart, $weekEnd) {
        $shifts = DB::table('schedules')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->get();

        $total = 0;
        $avg = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;

        foreach($shifts as $s) {
            if ($s->shift_type === 'DAY') $total += 9;
            else $total += $avg;
        }
        return $total;
    }

    private function userHasShiftToday($userId, $date) {
        return DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $date->format('Y-m-d'))
            ->exists();
    }

    private function workedLateYesterday($userId, $today) {
        $yesterday = $today->copy()->subDay()->format('Y-m-d');
        return DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $yesterday)
            ->where('shift_type', 'PM')
            ->exists();
    }

    private function worksEarlyTomorrow($userId, $today) {
        $tomorrow = $today->copy()->addDay()->format('Y-m-d');
        return DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $tomorrow)
            ->where('shift_type', 'AM')
            ->exists();
    }

    private function assignShift($date, $shift, $userId) {
        $exists = DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $date->format('Y-m-d'))
            ->exists();

        if (!$exists) {
            DB::table('schedules')->insert([
                'date' => $date->format('Y-m-d'),
                'shift_type' => $shift,
                'user_id' => $userId,
                'created_at' => now(), 'updated_at' => now()
            ]);
        }
    }

    public function clearSchedule() {
        DB::table('schedules')->truncate();
        return redirect('/nieuwegein/admin')->with('success', 'Het volledige rooster is gewist!');
    }

    // ==========================================
    // 5. OVERIGE ACTIES (CRUD)
    // ==========================================

    public function editUser($id) {
        $user = User::findOrFail($id);
        $currentAvailability = $user->availability()->first();
        $currentPreference = $currentAvailability ? $currentAvailability->shift_preference : 'BOTH';
        return view('team_edit', compact('user', 'currentPreference'));
    }

    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array'
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days,
        ]);

        Availability::where('user_id', $user->id)->update(['shift_preference' => $request->shift_preference]);
        return redirect('/nieuwegein/team')->with('success', 'Gegevens bijgewerkt!');
    }

    public function deleteUser($id) {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect('/nieuwegein/team')->with('success', 'Collega verwijderd.');
    }

    public function getDayDetails($date) {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->where('date', $date)
            ->select('users.name', 'schedules.shift_type', 'users.contract_hours', 'users.contract_days')
            ->orderBy('users.name')
            ->get();

        $grouped = [
            'AM' => [],
            'PM' => [],
            'DAY' => []
        ];

        foreach($schedules as $row) {
            $times = $this->calculateShiftTimes($date, $row->shift_type, $row->contract_hours, $row->contract_days);
            $startStr = Carbon::parse($times['start'])->format('H:i');
            $endStr   = Carbon::parse($times['end'])->format('H:i');
            $grouped[$row->shift_type][] = [
                'name' => $row->name,
                'time' => "$startStr - $endStr"
            ];
        }

        return response()->json($grouped);
    }
}