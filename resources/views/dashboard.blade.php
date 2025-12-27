@extends('layout')

@section('content')
    <style>
        /* --- ERP / Google Calendar Look Styling --- */
        
        #calendar { font-family: 'Segoe UI', system-ui, sans-serif; }

        /* Header Styling */
        .fc-col-header-cell { background-color: #f8fafc; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .fc-col-header-cell-cushion { text-decoration: none; color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Dagen Styling */
        .fc-daygrid-day-number { font-size: 0.85rem; color: #64748b; padding: 4px 8px; text-decoration: none; }
        .fc-daygrid-day:hover { background-color: #f8fafc; }
        .fc-day-today { background-color: #fff !important; }
        .fc-day-today .fc-daygrid-day-number { background-color: #0f6cbd; color: white; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; margin: 4px; }

        /* Event Chips */
        .fc-event { border: none !important; border-radius: 3px; font-size: 0.75rem; padding: 1px 4px; margin-bottom: 1px; cursor: pointer; box-shadow: none; line-height: 1.4; }
        .fc-event-main { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
        
        /* Links & Popovers */
        .fc-daygrid-more-link { font-size: 0.7rem; color: #64748b; font-weight: 600; text-decoration: none; padding-left: 4px; }
        .fc-daygrid-more-link:hover { text-decoration: underline; color: #0f6cbd; }
        .fc-popover { border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-radius: 6px; z-index: 1050; }
        .fc-popover-header { background-color: #f1f5f9; font-weight: 600; font-size: 0.9rem; padding: 8px 12px; color: #334155; }
        
        /* Progress Bars */
        .progress { box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); background-color: #f1f5f9; }
    </style>

    {{-- TOP BAR --}}
    <div class="top-bar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-week me-2 text-primary"></i>Dashboard</h5>
            
            {{-- Legenda --}}
            <div class="d-none d-md-flex align-items-center gap-2 ms-4 border-start ps-3">
                <span class="badge bg-light text-secondary border"><i class="bi bi-circle-fill text-primary me-1" style="font-size:0.5rem"></i> Ochtend</span>
                <span class="badge bg-light text-secondary border"><i class="bi bi-circle-fill text-success me-1" style="font-size:0.5rem"></i> Middag</span>
                <span class="badge bg-light text-secondary border"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem; color: #d68100;"></i> Dag</span>
            </div>
        </div>

        {{-- AUTO SCHEDULE BUTTON (Nieuw) --}}
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
            <i class="bi bi-magic me-1"></i> Genereer Rooster
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center py-2 px-3 rounded-1 border-0 border-start border-4 border-success shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            <small class="fw-medium">{{ session('success') }}</small>
        </div>
    @endif

    <div class="row g-3 h-100">
        {{-- Linker kolom: Kalender --}}
        <div class="col-lg-9">
            <div class="erp-card p-0 h-100 shadow-sm border-0">
                <div class="p-3 bg-white h-100 rounded-2">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>

        {{-- Rechter kolom: Uren Overzicht (Vernieuwd) --}}
        <div class="col-lg-3">
            <div class="erp-card h-100 shadow-sm border-0" style="max-height: 800px; overflow-y: auto;">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h6 class="fw-bold text-dark mb-0">Uren & Bezetting</h6>
                    <span class="badge bg-light text-muted border">Week {{ now()->weekOfYear }}</span>
                </div>
                
                <div class="vstack gap-3">
                    @foreach($stats as $user)
                        @php
                            $planned = $user->planned_hours_total;
                            $contract = $user->contract_hours;
                            $max = 40;

                            // Bereken percentage voor de balk (max 100% voor visualisatie)
                            $percent = ($contract > 0) ? ($planned / $contract) * 100 : 0;
                            // Als ze over contract gaan, bereken t.o.v. 40u of iets meer voor visualisatie
                            if($planned > $contract) {
                                $percent = ($planned / 45) * 100; // Schaal iets anders als ze erover zijn
                            }
                            if($percent > 100) $percent = 100;

                            // Kleur logica
                            $barColor = 'bg-primary'; // Standaard blauw
                            $textColor = 'text-muted';
                            
                            if ($planned > $contract) {
                                $barColor = 'bg-warning'; // Oranje (Let op: over contract)
                                $textColor = 'text-dark fw-bold';
                            }
                            if ($planned > $max) {
                                $barColor = 'bg-danger'; // Rood (Fout: over 40u)
                                $textColor = 'text-danger fw-bold';
                            }
                        @endphp

                        <div class="p-2 border rounded-1 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">{{ $user->name }}</span>
                                <span class="{{ $textColor }}" style="font-size: 0.75rem;">
                                    {{ $planned }} / {{ $contract }}u
                                </span>
                            </div>
                            
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            @if($planned > $max)
                                <div class="text-danger mt-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Maximaal 40u overschreden
                                </div>
                            @elseif($planned > $contract)
                                <div class="text-warning mt-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-info-circle-fill"></i> Over contracturen
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
{{-- MODAL 1: GENEREER ROOSTER (Aangepast met Periode Keuze) --}}
    <div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold"><i class="bi bi-magic me-2"></i>Auto Schedule</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="/nieuwegein/schedule/generate" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="small text-muted mb-3">Kies de startdatum en hoe ver vooruit je wilt plannen.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Startdatum</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" required 
                                   value="{{ \Carbon\Carbon::now()->next(\Carbon\Carbon::SATURDAY)->format('Y-m-d') }}">
                            <div class="form-text" style="font-size: 0.7rem;">Begint altijd op de dichtstbijzijnde zaterdag.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Periode</label>
                            <select name="duration" class="form-select form-select-sm">
                                <option value="1_week">1 Week</option>
                                <option value="3_months" selected>3 Maanden</option>
                                <option value="6_months">6 Maanden</option>
                                <option value="12_months">12 Maanden (1 Jaar)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer py-2 bg-light">
                        <button type="button" class="btn btn-sm btn-light text-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-sm btn-primary">Start Genereren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 2: DETAIL DAG (Bestaand, opgepoetst) --}}
    <div class="modal fade" id="dayDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-2">
                <div class="modal-header py-2 bg-light border-bottom">
                    <h6 class="modal-title fw-bold text-dark" id="modalDateTitle">Details</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="loadingSpinner" class="text-center p-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                    
                    <div id="detailsContent" class="d-none">
                        <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center">
                            <i class="bi bi-sunrise-fill text-primary me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">OCHTEND</span>
                        </div>
                        <ul id="listAM" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>

                        <div class="px-3 py-2 bg-light border-bottom border-top d-flex align-items-center">
                            <i class="bi bi-sun-fill text-warning me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">DAGDIENST</span>
                        </div>
                        <ul id="listDAY" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>

                        <div class="px-3 py-2 bg-light border-bottom border-top d-flex align-items-center">
                            <i class="bi bi-sunset-fill text-success me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">MIDDAG</span>
                        </div>
                        <ul id="listPM" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>
                    </div>

                    <div id="noEventsMessage" class="p-4 text-center text-muted small d-none">
                        Geen planning.
                    </div>
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
                buttonText: {
                    today: 'Vandaag',
                    month: 'Maand'
                },

                dayMaxEvents: 4, 
                moreLinkClick: 'popover', 
                fixedWeekCount: false, 
                showNonCurrentDates: false, 

                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
                events: '/nieuwegein/schedule/api',

                eventContent: function(arg) {
                    let timeText = arg.timeText;
                    let title = arg.event.title;
                    let html = `<div class="d-flex align-items-center overflow-hidden">`;
                    if(timeText) {
                        html += `<span class="opacity-75 me-1" style="font-size: 0.7em; min-width: 28px;">${timeText}</span>`;
                    }
                    html += `<span class="fw-medium text-truncate">${title}</span>`;
                    html += `</div>`;
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
                                        li.className = 'list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-0';
                                        li.innerHTML = `<span class="text-dark">${item.name}</span><span class="badge bg-light text-secondary border fw-normal">${item.time}</span>`;
                                        document.getElementById(listId).appendChild(li);
                                    });
                                } else {
                                    var li = document.createElement('li');
                                    li.className = 'list-group-item text-muted small fst-italic px-3 py-1 border-0';
                                    li.innerText = '-';
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