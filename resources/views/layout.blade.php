@extends('layout')

@section('content')
    <style>
        /* --- ZAKELIJKE ERP LAYOUT --- */
        
        #calendar { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }

        /* Header Styling (Strak grijs) */
        .fc-col-header-cell { 
            background-color: #f8fafc; 
            padding: 10px 0; 
            border-bottom: 2px solid #e2e8f0; 
        }
        .fc-col-header-cell-cushion { 
            text-decoration: none; 
            color: #334155; 
            font-weight: 600; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        /* Dagen Styling */
        .fc-daygrid-day-number { 
            font-size: 0.85rem; 
            color: #64748b; 
            padding: 8px; 
            text-decoration: none; 
            font-weight: 500; 
        }
        .fc-daygrid-day:hover { background-color: #f8fafc; }
        .fc-day-today { background-color: #fff !important; }
        
        /* Huidige dag indicator (Subtiel vierkant) */
        .fc-day-today .fc-daygrid-day-number { 
            background-color: #0f172a; 
            color: white; 
            border-radius: 2px; 
            width: 24px; 
            height: 24px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            margin: 4px; 
        }

        /* --- EVENTS: SAP/Oracle Stijl --- */
        /* Verwijder standaard styling */
        .fc-event { 
            background: transparent !important; 
            border: none !important; 
            box-shadow: none !important;
            margin-bottom: 2px;
            cursor: pointer;
        }

        /* De container voor de event (Witte balk met gekleurde rand links) */
        .erp-event-bar {
            background-color: #ffffff;
            border: 1px solid #cbd5e1; /* Dunne grijze rand rondom */
            border-left-width: 4px;    /* Dikke gekleurde rand links */
            padding: 2px 6px;
            font-size: 0.75rem;
            color: #1e293b;
            line-height: 1.3;
            border-radius: 1px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            transition: all 0.1s ease;
        }
        
        .erp-event-bar:hover {
            border-color: #94a3b8;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            z-index: 5;
            position: relative;
        }

        /* Event Tekst Styling */
        .erp-time { font-weight: 600; color: #475569; margin-right: 4px; font-size: 0.7rem; }
        .erp-title { font-weight: 500; color: #0f172a; }

        /* Links & Popovers */
        .fc-daygrid-more-link { font-size: 0.75rem; color: #475569; font-weight: 600; text-decoration: none; padding-left: 4px; }
        .fc-popover { border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 2px; z-index: 1050; }
        .fc-popover-header { background-color: #f1f5f9; font-weight: 600; padding: 8px 12px; color: #334155; border-bottom: 1px solid #e2e8f0; }
        
        /* Progress Bars */
        .progress { background-color: #e2e8f0; height: 6px; border-radius: 0; }
        .progress-bar { border-radius: 0; }
    </style>

    {{-- TOP BAR --}}
    <div class="top-bar d-flex justify-content-between align-items-center mb-4 p-3 bg-white border rounded-1 shadow-sm">
        <div class="d-flex align-items-center gap-4">
            <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">PLANNINGSOVERZICHT</h5>
            
            {{-- Zakelijke Legenda (Tekst only) --}}
            <div class="d-none d-md-flex align-items-center gap-4 border-start ps-4">
                <div class="d-flex align-items-center" style="font-size: 0.75rem;">
                    <div style="width: 12px; height: 12px; background: #0070d2; margin-right: 6px; border-radius: 1px;"></div>
                    <span class="text-secondary fw-bold text-uppercase">Ochtend</span>
                </div>
                <div class="d-flex align-items-center" style="font-size: 0.75rem;">
                    <div style="width: 12px; height: 12px; background: #d68100; margin-right: 6px; border-radius: 1px;"></div>
                    <span class="text-secondary fw-bold text-uppercase">Dag</span>
                </div>
                <div class="d-flex align-items-center" style="font-size: 0.75rem;">
                    <div style="width: 12px; height: 12px; background: #04844b; margin-right: 6px; border-radius: 1px;"></div>
                    <span class="text-secondary fw-bold text-uppercase">Middag</span>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary btn-sm px-4 rounded-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;" data-bs-toggle="modal" data-bs-target="#generateModal">
            Genereer Rooster
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center py-2 px-3 rounded-1 border-0 border-start border-4 border-success shadow-sm mb-4 bg-white">
            <span class="fw-medium text-dark" style="font-size: 0.85rem;">{{ session('success') }}</span>
        </div>
    @endif

    <div class="row g-4 h-100">
        {{-- Linker kolom: Kalender --}}
        <div class="col-lg-9">
            <div class="bg-white p-0 rounded-1 shadow-sm border h-100">
                <div class="p-3 border-bottom bg-light">
                    <span class="fw-bold text-secondary text-uppercase small">Kalenderweergave</span>
                </div>
                <div class="p-3">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>

        {{-- Rechter kolom: Uren Overzicht --}}
        <div class="col-lg-3">
            <div class="bg-white p-0 rounded-1 shadow-sm border h-100" style="max-height: 850px; overflow-y: auto;">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-secondary text-uppercase small">Weekstatus</span>
                    <span class="badge bg-white text-dark border rounded-0">W{{ now()->weekOfYear }}</span>
                </div>
                
                <div class="p-3 vstack gap-3">
                    @foreach($stats as $user)
                        @php
                            $planned = $user->planned_hours_total;
                            $contract = $user->contract_hours;
                            $max = 40;
                            $percent = ($contract > 0) ? ($planned / $contract) * 100 : 0;
                            if($planned > $contract) $percent = ($planned / 45) * 100;
                            if($percent > 100) $percent = 100;

                            $barColor = 'bg-primary'; 
                            $statusText = 'OK';
                            $statusClass = 'text-success';
                            
                            if ($planned > $max) {
                                $barColor = 'bg-danger';
                                $statusText = 'FOUT (>40u)';
                                $statusClass = 'text-danger fw-bold';
                            } elseif ($planned > $contract) {
                                $barColor = 'bg-warning';
                                $statusText = 'OVERUREN';
                                $statusClass = 'text-warning fw-bold';
                            }
                        @endphp

                        <div class="p-2 border rounded-0 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="fw-bold text-dark" style="font-size: 0.8rem;">{{ $user->name }}</div>
                                <div class="small text-secondary" style="font-size: 0.75rem;">{{ $planned }} / {{ $contract }}</div>
                            </div>
                            
                            <div class="progress mb-2" style="height: 4px;">
                                <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $percent }}%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center" style="font-size: 0.65rem;">
                                <span class="text-uppercase text-muted">Status</span>
                                <span class="{{ $statusClass }}">{{ $statusText }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 1: GENEREER ROOSTER --}}
    <div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-0">
                <div class="modal-header bg-dark text-white py-2 rounded-0">
                    <h6 class="modal-title fw-bold text-uppercase small" style="letter-spacing: 1px;">Rooster Genereren</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="/nieuwegein/schedule/generate" method="POST">
                    @csrf
                    <div class="modal-body bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary text-uppercase">Startdatum</label>
                            <input type="date" name="start_date" class="form-control form-control-sm rounded-0 border-secondary" required 
                                   value="{{ \Carbon\Carbon::now()->next(\Carbon\Carbon::SATURDAY)->format('Y-m-d') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold small text-secondary text-uppercase">Looptijd</label>
                            <select name="duration" class="form-select form-select-sm rounded-0 border-secondary">
                                <option value="1_week">1 Week</option>
                                <option value="3_months" selected>3 Maanden</option>
                                <option value="6_months">6 Maanden</option>
                                <option value="12_months">12 Maanden</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer py-2 bg-white rounded-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-0" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-0 px-3">Uitvoeren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 2: DETAIL DAG --}}
    <div class="modal fade" id="dayDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow rounded-0">
                <div class="modal-header py-2 bg-light border-bottom rounded-0">
                    <h6 class="modal-title fw-bold text-dark text-uppercase small" id="modalDateTitle">Details</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="loadingSpinner" class="text-center p-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                    
                    <div id="detailsContent" class="d-none">
                        <div class="bg-light p-2 border-bottom fw-bold text-secondary" style="font-size: 0.7rem; letter-spacing: 0.5px;">OCHTEND (AM)</div>
                        <ul id="listAM" class="list-group list-group-flush small"></ul>

                        <div class="bg-light p-2 border-bottom border-top fw-bold text-secondary" style="font-size: 0.7rem; letter-spacing: 0.5px;">DAGDIENST</div>
                        <ul id="listDAY" class="list-group list-group-flush small"></ul>

                        <div class="bg-light p-2 border-bottom border-top fw-bold text-secondary" style="font-size: 0.7rem; letter-spacing: 0.5px;">MIDDAG (PM)</div>
                        <ul id="listPM" class="list-group list-group-flush small"></ul>
                    </div>
                    <div id="noEventsMessage" class="p-4 text-center text-muted small d-none">Geen planning.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var dayDetailsModal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                firstDay: 6, 
                height: 'auto',
                contentHeight: 700, 
                
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                buttonText: { today: 'Vandaag', month: 'Maand' },

                dayMaxEvents: 4, 
                moreLinkClick: 'popover', 
                fixedWeekCount: false, 
                showNonCurrentDates: false, 

                events: '/nieuwegein/schedule/api',

                // --- STRIKT ZAKELIJKE LAYOUT (GEEN EMOJIS, GEEN ICONEN) ---
                eventContent: function(arg) {
                    let title = arg.event.title; 
                    
                    // We bepalen de border-color op basis van de titel tekst
                    // Omdat we geen kleurcode hebben in 'arg', doen we het op basis van de naam conventie
                    // of we gebruiken de backgroundColor property als die meegegeven wordt uit de backend.
                    
                    let borderColor = '#64748b'; // Default grijs
                    // We checken de 'color' prop die vanuit de controller komt (die is blauw/groen/oranje)
                    if(arg.event.backgroundColor) {
                        borderColor = arg.event.backgroundColor;
                    }

                    // Tijd extractie (staat niet altijd los in arg.timeText bij month view, dus we halen het uit de tekst als het moet, of laten het leeg)
                    // In de controller gaven we tijden mee, maar FullCalendar month view toont standaard geen tijd.
                    // We tonen alleen de naam.
                    
                    // Schoon de titel op (verwijder haakjes zoals (Ochtend) voor een schonere look)
                    let cleanTitle = title.replace(/\(Ochtend\)|\(Middag\)|\(Dag\)/g, '').trim();

                    let html = `
                        <div class="erp-event-bar" style="border-left-color: ${borderColor};">
                            <span class="erp-title">${cleanTitle}</span>
                        </div>
                    `;
                    
                    return { html: html };
                },

                dateClick: function(info) {
                    var clickedDate = info.dateStr;
                    var dateObj = new Date(clickedDate);
                    var options = { weekday: 'long', day: 'numeric', month: 'long' };
                    document.getElementById('modalDateTitle').innerText = dateObj.toLocaleDateString('nl-NL', options);

                    document.getElementById('loadingSpinner').classList.remove('d-none');
                    document.getElementById('detailsContent').classList.add('d-none');
                    document.getElementById('noEventsMessage').classList.add('d-none');
                    document.getElementById('listAM').innerHTML = '';
                    document.getElementById('listPM').innerHTML = '';
                    document.getElementById('listDAY').innerHTML = '';

                    dayDetailsModal.show();

                    fetch('/nieuwegein/schedule/day/' + clickedDate)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('loadingSpinner').classList.add('d-none');
                            var hasEvents = false;
                            
                            function fillList(listId, items) {
                                if (items && items.length > 0) {
                                    hasEvents = true;
                                    items.forEach(item => {
                                        var li = document.createElement('li');
                                        li.className = 'list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-bottom-0 rounded-0';
                                        // GEEN ICONEN HIER, ALLEEN TEKST
                                        li.innerHTML = `
                                            <span class="text-dark fw-medium" style="font-size: 0.8rem;">${item.name}</span>
                                            <span class="text-muted" style="font-size: 0.75rem;">${item.time}</span>
                                        `;
                                        document.getElementById(listId).appendChild(li);
                                    });
                                } else {
                                    var li = document.createElement('li');
                                    li.className = 'list-group-item text-muted fst-italic px-3 py-1 border-0 small';
                                    li.innerText = 'Geen diensten';
                                    document.getElementById(listId).appendChild(li);
                                }
                            }
                            fillList('listAM', data.AM);
                            fillList('listDAY', data.DAY);
                            fillList('listPM', data.PM);

                            if(hasEvents) {
                                document.getElementById('detailsContent').classList.remove('d-none');
                            } else {
                                document.getElementById('noEventsMessage').classList.remove('d-none');
                            }
                        });
                }
            });
            calendar.render();
        });
    </script>
@endsection