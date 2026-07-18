<?php
$conn->query("UPDATE bookings b
    JOIN time_slots ts ON b.time_slot_id = ts.id
    SET b.status = 'Completed'
    WHERE b.status = 'Confirmed'
    AND (b.event_date < CURDATE()
        OR (b.event_date = CURDATE() AND ts.end_time < CURTIME()))");
