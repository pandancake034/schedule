@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Dashboard / Rooster</h5>
    </div>

    <div class="erp-card">
        <div id='calendar'></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                height: 700,
                events: '/nieuwegein/schedule/api' 
            });
            calendar.render();
        });
    </script>
@endsection
