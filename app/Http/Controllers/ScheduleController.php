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
        // Statistieken ophalen voor de huidige maand
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // We halen alle shifts op van deze maand en berekenen de uren per persoon
        $users = User::with(['schedules' => function($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
        }])->get();

        // Bereken gewerkte uren op basis van contract
        $users->map(function($user) {
            // Voorkom delen door nul
            if ($user->contract_days > 0) {
                $hoursPerShift = $user->contract_hours / $user->contract_days;
            } else {
                $hoursPerShift = 0;
            }
            
            $user->worked_hours = $user->schedules->count() * $hoursPerShift;
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
    // 2. API (VOOR DE KALENDER - DYNAMISCHE TIJDEN)
    // ==========================================

    public function getEvents() {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->select(
                'schedules.date', 
                'schedules.shift_type', 
                'users.name', 
                'users.contract_hours', 
                'users.contract_days'
            )
            ->get();

        $events = $schedules->map(function($row) {
            $color = ($row->shift_type == 'AM') ? '#0070d2' : '#04844b';
            
            // Bereken uren per dag voor deze specifieke gebruiker
            $hoursPerDay = ($row->contract_days > 0) ? ($row->contract_hours / $row->contract_days) : 0;

            // Bepaal start en eindtijden
            $date = $row->date;
            
            if ($row->shift_type == 'AM') {
                // AM begint om 05:00
                $start = Carbon::parse("$date 05:00:00");
                $end   = $start->copy()->addHours($hoursPerDay); // Bijv. 05:00 + 8u = 13:00
            } else {
                // PM begint om 14:00
                $start = Carbon::parse("$date 14:00:00");
                $end   = $start->copy()->addHours($hoursPerDay); // Bijv. 14:00 + 9u = 23:00
            }

            return [
                'title' => $row->name . ' (' . $row->shift_type . ')',
                'start' => $start->toIso8601String(),
                'end'   => $end->toIso8601String(),
                'color' => $color
            ];
        });

        return response()->json($events);
    }

    // ==========================================
    // 3. ACTIES (OPSLAAN, GENEREREN & WISSEN)
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

    /**
     * HET VERNIEUWDE ALGORITME
     * - Werkweek start op ZATERDAG
     * - Uren per shift = contract / dagen
     */
    public function generateSchedule(Request $request) {
        // We verwachten een startdatum (liefst een zaterdag, maar we corrigeren anders)
        $startDate = Carbon::parse($request->start_date);
        
        // Loop 7 dagen
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $yesterdayDate = $currentDate->copy()->subDay()->format('Y-m-d');
            $dayNameEnglish = $currentDate->format('l'); 

            // Bezettingsregel: Do/Vr niemand (tenzij vast), rest 2 man
            if ($dayNameEnglish == 'Thursday' || $dayNameEnglish == 'Friday') {
                $neededPerShift = 0; 
            } else {
                $neededPerShift = 2;
            }

            foreach(['AM', 'PM'] as $shift) {
                
                // --- STAP 2: VASTE DAGEN ---
                $fixedUsers = User::whereJsonContains('fixed_days', $dayNameEnglish)->get();

                foreach($fixedUsers as $fixedUser) {
                    // Check Rusttijd: Gisteren PM? Dan vandaag geen AM.
                    if ($shift == 'AM') {
                        $workedLateYesterday = DB::table('schedules')
                            ->where('user_id', $fixedUser->id)
                            ->where('date', $yesterdayDate)
                            ->where('shift_type', 'PM')
                            ->exists();
                        
                        if ($workedLateYesterday) continue; 
                    }

                    $exists = DB::table('schedules')
                        ->where('user_id', $fixedUser->id)
                        ->where('date', $currentDate->format('Y-m-d'))
                        ->where('shift_type', $shift)
                        ->exists();
                    
                    if (!$exists) {
                        $this->assignShift($currentDate, $shift, $fixedUser->id);
                    }
                }

                // --- STAP 3: AANVULLEN ---
                $currentCount = DB::table('schedules')
                    ->where('date', $currentDate->format('Y-m-d'))
                    ->where('shift_type', $shift)
                    ->count();

                $slotsToFill = $neededPerShift - $currentCount;

                if ($slotsToFill > 0) {
                    $candidates = User::whereHas('availability', function($query) use ($dayNameEnglish, $shift) {
                        $query->where('day_of_week', $dayNameEnglish)
                              ->whereIn('shift_preference', [$shift, 'BOTH']);
                    })
                    ->get()
                    ->filter(function ($user) use ($shift, $currentDate, $yesterdayDate) {
                        // BEREKENINGEN
                        // 1. Wat is de duur van een shift voor deze persoon?
                        $hoursPerShift = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;

                        // 2. Definieer de werkweek (Zaterdag t/m Vrijdag)
                        // Als de huidige dag een zaterdag is, is dit het begin.
                        // Anders zoeken we de voorafgaande zaterdag.
                        $startOfWeek = $currentDate->copy();
                        if ($startOfWeek->dayOfWeek != Carbon::SATURDAY) {
                            $startOfWeek->previous(Carbon::SATURDAY);
                        }
                        $endOfWeek = $startOfWeek->copy()->addDays(6); // Vrijdag

                        // 3. Hoeveel heeft deze persoon al gepland staan in deze werkweek?
                        $shiftsThisWeek = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                            ->count();

                        $hoursPlannedThisWeek = $shiftsThisWeek * $hoursPerShift;

                        // CHECK 1: Uren limiet
                        // Als we deze shift toevoegen, gaan we dan over het contract heen?
                        if (($hoursPlannedThisWeek + $hoursPerShift) > $user->contract_hours) {
                            return false; 
                        }

                        // CHECK 2: Dagen limiet
                        // Mag hij nog een dag werken?
                        $daysPlanned = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                            ->distinct('date')
                            ->count();

                        // Werkt hij vandaag al? (Dan is het geen extra dag)
                        $worksToday = DB::table('schedules')
                            ->where('user_id', $user->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->exists();

                        if (!$worksToday && $daysPlanned >= $user->contract_days) {
                            return false;
                        }

                        // CHECK 3: Rusttijd (Gisteren PM -> Vandaag geen AM)
                        if ($shift == 'AM') {
                            $workedLateYesterday = DB::table('schedules')
                                ->where('user_id', $user->id)
                                ->where('date', $yesterdayDate)
                                ->where('shift_type', 'PM')
                                ->exists();
                            if ($workedLateYesterday) return false;
                        }

                        return true;
                    })
                    ->shuffle()
                    ->take($slotsToFill);

                    foreach($candidates as $candidate) {
                        $exists = DB::table('schedules')
                            ->where('user_id', $candidate->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->where('shift_type', $shift)
                            ->exists();

                        if (!$exists) {
                            $this->assignShift($currentDate, $shift, $candidate->id);
                        }
                    }
                }
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster gegenereerd (werkweek za-vr)!');
    }

    private function assignShift($date, $shift, $userId) {
        DB::table('schedules')->insert([
            'date' => $date->format('Y-m-d'),
            'shift_type' => $shift,
            'user_id' => $userId,
            'created_at' => now(), 'updated_at' => now()
        ]);
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
}