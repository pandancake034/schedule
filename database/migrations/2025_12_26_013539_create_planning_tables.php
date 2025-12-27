// Zoek deze regel in availabilities:
$table->enum('shift_preference', ['AM', 'PM', 'BOTH', 'DAY']); // 'DAY' toegevoegd

// Zoek deze regel in schedules:
$table->enum('shift_type', ['AM', 'PM', 'DAY']); // 'DAY' toegevoegd