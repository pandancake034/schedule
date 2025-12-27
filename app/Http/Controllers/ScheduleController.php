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

    public function generateSchedule(Request $request) {
        $startDate = Carbon::parse($request->start_date);
        
        // Loop 7 dagen vanaf startdatum
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayNameEnglish = $currentDate->format('l'); 

            // ============================================================
            // DONDERDAG & VRIJDAG: 'DAY' SHIFT (09:00 - 18:00)
            // ============================================================
            if ($dayNameEnglish == 'Thursday' || $dayNameEnglish == 'Friday') {
                
                // 1. Zoek vaste mensen voor deze dag
                $fixedUsers = User::whereJsonContains('fixed_days', $dayNameEnglish)->get();
                
                foreach($fixedUsers as $user) {
                    $this->assignShift($currentDate, 'DAY', $user->id);
                }

                // (Optioneel: Als je hier ook flexwerkers wilt, kun je die logica hier toevoegen.
                // Voor nu plannen we alleen de vaste mensen in op Do/Vr zoals gevraagd.)

                continue; // Ga naar volgende dag, want Do/Vr hebben geen AM/PM
            }

            // ============================================================
            // OVERIGE DAGEN: AM & PM SHIFTS (2 personen per shift)
            // ============================================================
            
            foreach(['AM', 'PM'] as $shift) {
                // Eerst vaste mensen
                $fixedUsers = User::whereJsonContains('fixed_days', $dayNameEnglish)->get();
                foreach($fixedUsers as $fixedUser) {
                    // Check voorkeur
                    $pref = $fixedUser->availability()->where('day_of_week', $dayNameEnglish)->first()->shift_preference ?? 'BOTH';
                    if ($pref == 'BOTH' || $pref == $shift) {
                        $this->assignShift($currentDate, $shift, $fixedUser->id);
                    }
                }

                // Dan aanvullen tot 2 personen
                $currentCount = DB::table('schedules')
                    ->where('date', $currentDate->format('Y-m-d'))
                    ->where('shift_type', $shift)
                    ->count();

                $slotsToFill = 2 - $currentCount;

                if ($slotsToFill > 0) {
                    $candidates = User::whereHas('availability', function($query) use ($dayNameEnglish, $shift) {
                        $query->where('day_of_week', $dayNameEnglish)
                              ->whereIn('shift_preference', [$shift, 'BOTH']);
                    })->get()
                    ->filter(function ($user) use ($shift, $currentDate) {
                        
                        // BEREKENING: Contract uren check
                        $hoursPerShift = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;
                        
                        // Werkweek bepalen (Zaterdag t/m Vrijdag)
                        $startOfWeek = $currentDate->copy();
                        if ($startOfWeek->dayOfWeek != Carbon::SATURDAY) {
                            $startOfWeek->previous(Carbon::SATURDAY);
                        }
                        $endOfWeek = $startOfWeek->copy()->addDays(6);

                        // Tel reeds geplande shifts deze week
                        $shiftsThisWeek = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                            ->count();

                        // Als we over de contracturen heen gaan -> niet inplannen
                        if (($shiftsThisWeek * $hoursPerShift) + $hoursPerShift > $user->contract_hours) {
                            return false; 
                        }

                        // Maximaal contract dagen check
                        $daysPlanned = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                            ->distinct('date')
                            ->count();

                        $worksToday = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->exists();

                        if (!$worksToday && $daysPlanned >= $user->contract_days) {
                            return false;
                        }

                        // Rusttijd Check (Gisteren PM -> Vandaag geen AM)
                        if ($shift == 'AM') {
                            $yesterday = $currentDate->copy()->subDay()->format('Y-m-d');
                            $workedLate = DB::table('schedules')
                                ->where('user_id', $user->id)
                                ->where('date', $yesterday)
                                ->where('shift_type', 'PM')
                                ->exists();
                            if ($workedLate) return false;
                        }

                        return true;
                    })
                    ->shuffle()
                    ->take($slotsToFill);

                    foreach($candidates as $candidate) {
                        $this->assignShift($currentDate, $shift, $candidate->id);
                    }
                }
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster succesvol gegenereerd!');
    }

    // Helper om dubbele shifts te voorkomen
    private function assignShift($date, $shift, $userId) {
        $exists = DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $date->format('Y-m-d'))
            ->where('shift_type', $shift)
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