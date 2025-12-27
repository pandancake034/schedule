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
        // We halen gebruikers op en tellen hun shifts
        // We kijken naar ALLE toekomstige en huidige shifts om de teller te vullen
        $users = User::with('schedules')->get();

        // Bereken gewerkte uren
        $users->map(function($user) {
            $totalHours = 0;

            foreach($user->schedules as $schedule) {
                if ($schedule->shift_type === 'DAY') {
                    // Donderdag/Vrijdag dienst is altijd 9 uur (09:00 - 18:00)
                    $totalHours += 9;
                } else {
                    // AM/PM dienst: Uren = Contract Uren / Contract Dagen
                    // Bijv: 32 uur / 4 dagen = 8 uur per shift
                    $hoursPerShift = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;
                    $totalHours += $hoursPerShift;
                }
            }
            
            $user->planned_hours_total = $totalHours;
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
            // Kleuren: Ochtend=Blauw, Middag=Groen, Dagdienst=Oranje
            if ($row->shift_type == 'AM') $color = '#0070d2';
            elseif ($row->shift_type == 'PM') $color = '#04844b';
            else $color = '#d68100'; // DAY shift

            // Bereken uren voor AM/PM
            $hoursPerDay = ($row->contract_days > 0) ? ($row->contract_hours / $row->contract_days) : 0;

            $date = $row->date;
            
            if ($row->shift_type == 'DAY') {
                // Donderdag/Vrijdag: 09:00 - 18:00
                $start = Carbon::parse("$date 09:00:00");
                $end   = Carbon::parse("$date 18:00:00");
                $title = $row->name . ' (Dag)';
            } elseif ($row->shift_type == 'AM') {
                // AM: Start 05:00
                $start = Carbon::parse("$date 05:00:00");
                $end   = $start->copy()->addHours($hoursPerDay);
                $title = $row->name . ' (Ochtend)';
            } else {
                // PM: Start 14:00
                $start = Carbon::parse("$date 14:00:00");
                $end   = $start->copy()->addHours($hoursPerDay);
                $title = $row->name . ' (Middag)';
            }

            return [
                'title' => $title,
                'start' => $start->toIso8601String(),
                'end'   => $end->toIso8601String(),
                'color' => $color
            ];
        });

        return response()->json($events);
    }

    // ==========================================
    // 3. ACTIES (OPSLAAN & GENEREREN)
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
    // 4. NIEUWE GENERATE LOGICA
    // ==========================================

    public function generateSchedule(Request $request)
    {
        // 1. Bepaal de weekrange (Start moet op Zaterdag vallen)
        $startDate = Carbon::parse($request->start_date);
        
        if ($startDate->dayOfWeek !== Carbon::SATURDAY) {
            // Zorg dat we altijd met de zaterdag van die week beginnen
            $startDate->previous(Carbon::SATURDAY); 
        }

        $weekStart = $startDate->copy();
        $weekEnd   = $startDate->copy()->addDays(6);

        // 2. Loop door elke dag van de week (Za t/m Vr)
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->format('l'); // Bijv. 'Monday'

            // Bepaal welke shifts er vandaag zijn
            // Do & Vr: alleen DAG (DAY). Andere dagen: AM & PM.
            if ($dayName === 'Thursday' || $dayName === 'Friday') {
                $shiftsToCheck = ['DAY'];
            } else {
                $shiftsToCheck = ['AM', 'PM'];
            }

            // STAP A: Vaste medewerkers inplannen (Prioriteit)
            $this->scheduleFixedUsers($currentDate, $shiftsToCheck, $weekStart, $weekEnd);

            // STAP B: Rooster opvullen tot 2 personen per shift
            foreach ($shiftsToCheck as $shiftType) {
                $this->fillShiftGaps($currentDate, $shiftType, $weekStart, $weekEnd);
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster gegenereerd volgens de nieuwe regels!');
    }

    /**
     * Stap A: Plan mensen in die deze dag als VASTE dag hebben.
     */
    private function scheduleFixedUsers($date, $availableShifts, $weekStart, $weekEnd)
    {
        $dayName = $date->format('l');

        // Haal gebruikers op die deze dag vast werken
        // We filteren hier alvast op JSON die de dagnaam bevat
        $fixedUsers = User::whereJsonContains('fixed_days', $dayName)->get();

        foreach ($fixedUsers as $user) {
            // Check 1: Heeft deze persoon vandaag al een dienst? (Voorkom dubbel)
            if ($this->userHasShiftToday($user->id, $date)) {
                continue;
            }

            // Bepaal welke shift ze krijgen
            $targetShift = null;

            if (in_array('DAY', $availableShifts)) {
                $targetShift = 'DAY';
            } else {
                // Het is een AM/PM dag. Kijk naar voorkeur.
                $pref = Availability::where('user_id', $user->id)
                    ->where('day_of_week', $dayName)
                    ->value('shift_preference') ?? 'BOTH';

                // Logica: Als voorkeur AM is, probeer AM. Anders PM. 
                // Als BOTH: kijk of ze gisteren PM werkten (dan moeten ze nu PM).
                if ($pref === 'AM') $targetShift = 'AM';
                elseif ($pref === 'PM') $targetShift = 'PM';
                else {
                    // Bij BOTH: Check rusttijd. Als gisteren PM was, moet het vandaag PM zijn.
                    if ($this->workedLateYesterday($user->id, $date)) {
                        $targetShift = 'PM';
                    } else {
                        // Anders vullen we gewoon de eerste in (bijv AM), de fill-logica balanceert de rest wel.
                        $targetShift = 'AM';
                    }
                }
            }

            // Voer laatste controles uit (contract en rusttijd) en plan in
            // isFixed=true zorgt ervoor dat contracturen minder strikt zijn bij vaste dagen, 
            // maar rusttijden blijven heilig.
            if ($this->canWorkShift($user, $date, $targetShift, $weekStart, $weekEnd, true)) {
                $this->assignShift($date, $targetShift, $user->id);
            }
        }
    }

    /**
     * Stap B: Vul de gaten op tot we 2 man per shift hebben.
     */
    private function fillShiftGaps($date, $shiftType, $weekStart, $weekEnd)
    {
        $targetPerShift = 2; // We willen altijd 2 leiders

        // Hoeveel hebben we er al?
        $currentCount = DB::table('schedules')
            ->where('date', $date->format('Y-m-d'))
            ->where('shift_type', $shiftType)
            ->count();

        $needed = $targetPerShift - $currentCount;

        if ($needed <= 0) return;

        // Zoek kandidaten
        // 1. Moet beschikbaar zijn op deze dag (via Availability tabel)
        // 2. Mag nog geen vaste dag hebben vandaag (want die zijn al gedaan of hadden niet gekund)
        $dayName = $date->format('l');
        
        $candidates = User::whereHas('availability', function($q) use ($dayName, $shiftType) {
            $q->where('day_of_week', $dayName);
            
            // Als we AM zoeken, moet voorkeur AM of BOTH zijn.
            if ($shiftType !== 'DAY') {
                $q->whereIn('shift_preference', [$shiftType, 'BOTH']);
            }
        })
        ->inRandomOrder() // Zorgt voor variatie
        ->get();

        foreach ($candidates as $candidate) {
            if ($needed <= 0) break;

            // Check alle harde regels (contract, rusttijd, dubbele shift)
            if ($this->canWorkShift($candidate, $date, $shiftType, $weekStart, $weekEnd)) {
                $this->assignShift($date, $shiftType, $candidate->id);
                $needed--;
            }
        }
    }

    /**
     * De centrale check-functie voor ALLE regels.
     */
    private function canWorkShift($user, $date, $shiftType, $weekStart, $weekEnd, $isFixed = false)
    {
        // 1. Dubbele shift check: Werkt deze persoon vandaag al?
        if ($this->userHasShiftToday($user->id, $date)) {
            return false; 
        }

        // 2. Rusttijd check: Gisteren PM -> Vandaag Ochtend (AM) mag NIET.
        if ($shiftType === 'AM' && $this->workedLateYesterday($user->id, $date)) {
            return false;
        }

        // 3. Contract Checks (Uren & Dagen)
        // Tel huidige geplande dagen/uren in deze week
        $shiftsThisWeek = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->get();

        $daysWorkedCount = $shiftsThisWeek->count();
        
        // Bereken totaal geplande uren
        $hoursWorked = 0;
        foreach($shiftsThisWeek as $s) {
            $hoursWorked += $this->calculateShiftHours($s->shift_type, $user);
        }

        // Uren van de NIEUWE shift die we willen inplannen
        $newShiftHours = $this->calculateShiftHours($shiftType, $user);

        // Check: Dagen limiet
        if ($daysWorkedCount >= $user->contract_days) {
            return false;
        }

        // Check: Uren limiet
        if (($hoursWorked + $newShiftHours) > $user->contract_hours) {
            return false;
        }

        return true;
    }

    // ==========================================
    // HELPER FUNCTIES
    // ==========================================

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
            ->where('shift_type', 'PM') // Laat gewerkt
            ->exists();
    }

    private function calculateShiftHours($shiftType, $user) {
        if ($shiftType === 'DAY') {
            return 9; // Dagdienst is vast 9 uur (09:00 - 18:00)
        }
        // AM/PM is contract afhankelijk (bijv 32u / 4d = 8u)
        return ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;
    }

    private function assignShift($date, $shift, $userId) {
        // Dubbelcheck voor de zekerheid (race conditions)
        // Een gebruiker mag maar 1 dienst per dag hebben
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
    // 5. OVERIGE ACTIES (EDIT/DELETE)
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

    /**
     * Haalt details op voor een specifieke datum (voor de pop-up).
     */
    public function getDayDetails($date) {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->where('date', $date)
            ->select('users.name', 'schedules.shift_type', 'users.contract_hours', 'users.contract_days')
            ->orderBy('users.name')
            ->get();

        // Groepeer de data zodat we het makkelijk kunnen tonen
        $grouped = [
            'AM' => [],
            'PM' => [],
            'DAY' => []
        ];

        foreach($schedules as $row) {
            // Bereken eindtijd voor weergave
            $hoursPerShift = ($row->contract_days > 0) ? ($row->contract_hours / $row->contract_days) : 0;
            
            if ($row->shift_type == 'AM') {
                $timeStr = '05:00 - ' . Carbon::parse('05:00')->addHours($hoursPerShift)->format('H:i');
            } elseif ($row->shift_type == 'PM') {
                $timeStr = '14:00 - ' . Carbon::parse('14:00')->addHours($hoursPerShift)->format('H:i');
            } else {
                $timeStr = '09:00 - 18:00';
            }

            $grouped[$row->shift_type][] = [
                'name' => $row->name,
                'time' => $timeStr
            ];
        }

        return response()->json($grouped);
    }
}