<?php
declare(strict_types=1);

function roleHome(string $role): string
{
    return match ($role) {
        'creator' => 'dashboard.php',
        'staff' => 'staff.php',
        'admin' => 'admin.php',
        default => 'index.php',
    };
}

function guardedPages(): array
{
    return [
        'dashboard.php' => ['creator'],
        'event_form.php' => ['creator', 'staff'],
        'attendees.php' => ['creator', 'staff'],
        'checkin.php' => ['creator', 'staff'],
        'organiser_action.php' => ['creator'],
        'staff.php' => ['staff'],
        'admin.php' => ['admin'],
        'admin_action.php' => ['admin'],
        'my_bookings.php' => ['user'],
        'booked.php' => ['user'],
        'booking_action.php' => ['user'],
        'cancel_booking.php' => ['user'],
    ];
}

function pageAllowed(?array $user, string $page): bool
{
    $allowed = guardedPages()[basename($page)] ?? null;
    if ($allowed === null || $user === null) {
        return false;
    }
    return in_array($user['role'] ?? '', $allowed, true);
}
