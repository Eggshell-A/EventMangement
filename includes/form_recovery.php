<?php
declare(strict_types=1);

/** Recovery destinations are GET-only pages from this allowlist, never submitted URLs or referrers. */
function formRecoveryView(array $server, array $config): array {
    $script=(string)($server['SCRIPT_NAME']??'/index.php');
    $pages=[
        'login.php'=>['login.php','Open a new login form'],
        'register.php'=>['register.php','Open a new sign-up form'],
        'forgot_password.php'=>['forgot_password.php','Request a new reset link'],
        'reset_password.php'=>['forgot_password.php','Request a new reset link'],
        'verify_email.php'=>['account.php','Open your account'],
        'account.php'=>['account.php','Open your account'],
        'delete_account.php'=>['account.php','Review your account'],
        'change_initial_password.php'=>['change_initial_password.php','Open the password form'],
        'mfa.php'=>['mfa.php','Open authenticator settings'],
        'mfa_challenge.php'=>['login.php','Start sign-in again'],
        'reauth.php'=>['reauth.php','Confirm your identity'],
        'admin_action.php'=>['admin.php','Return to admin'],
        'developer.php'=>['developer.php','Open the table explorer'],
        'billing.php'=>['billing.php','Return to billing'],
        'booking_action.php'=>['my_bookings.php','Open your bookings'],
        'cancel_booking.php'=>['my_bookings.php','Open your bookings'],
        'leave_waitlist.php'=>['my_bookings.php','Open your bookings'],
        'ticket_qr.php'=>['my_bookings.php','Open your bookings'],
        'scan.php'=>['scan.php','Open the scanner'],
        'checkin.php'=>['scan.php','Open the scanner'],
        'notifications.php'=>['notifications.php','Open your updates'],
        'logout.php'=>['account.php','Open your account'],
    ];
    [$page,$label]=$pages[basename($script)]??['index.php','Return to Fairboard'];
    // Assets stay on the current origin even if the official address uses a public HTTPS tunnel.
    $parts=explode('/',trim(str_replace('\\','/',dirname($script)),'/'));
    if(in_array('.', $parts,true)||in_array('..',$parts,true))$parts=[];
    $parts=array_values(array_filter($parts,static fn($part)=>$part!==''));
    $assetBase='/'.($parts?implode('/',array_map('rawurlencode',$parts)).'/':'').'assets/';
    $base=rtrim((string)$config['base_url'],'/').'/';
    return ['url'=>$base.$page,'label'=>$label,'home_url'=>$base.'index.php','asset_base'=>$assetBase];
}

/** Runs before database access or mutation; do not load the authenticated navigation/header here. */
function renderFormRecovery(): never {
    global $config;
    $view=formRecoveryView($_SERVER,$config);
    $escape=static fn(string $text):string=>htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__.'/form_recovery_page.php';
    exit;
}
