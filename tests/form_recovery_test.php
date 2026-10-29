<?php
require __DIR__.'/../includes/form_recovery.php';
$passed=0;
function verifyRecovery(bool $ok,string $label):void{global $passed;if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS $label\n";}
$config=['base_url'=>'https://events.example/EventLink'];
$view=formRecoveryView(['SCRIPT_NAME'=>'/EventLink/login.php','HTTP_HOST'=>'attacker.example','HTTP_REFERER'=>'https://attacker.example/steal'],$config);
verifyRecovery($view['url']==='https://events.example/EventLink/login.php','login recovery uses configured origin, not Host or Referer');
verifyRecovery($view['asset_base']==='/EventLink/assets/','assets work under a deployment subdirectory');
$view=formRecoveryView(['SCRIPT_NAME'=>'/login.php'],$config);
verifyRecovery($view['asset_base']==='/assets/','assets stay on the current origin rather than the canonical tunnel host');
$view=formRecoveryView(['SCRIPT_NAME'=>'/EventLink/reset_password.php','QUERY_STRING'=>'token=private-token&next=https://attacker.example'],$config);
verifyRecovery($view['url']==='https://events.example/EventLink/forgot_password.php','expired reset form requests a fresh link without reflecting its token');
$view=formRecoveryView(['SCRIPT_NAME'=>'/EventLink/delete_account.php'],$config);
verifyRecovery($view['url']==='https://events.example/EventLink/account.php','deletion recovery returns to account review, never replays deletion');
$view=formRecoveryView(['SCRIPT_NAME'=>'/EventLink/admin_action.php'],$config);
verifyRecovery($view['url']==='https://events.example/EventLink/admin.php','admin mutations return to a GET page');
$view=formRecoveryView(['SCRIPT_NAME'=>'/unknown.php','HTTP_REFERER'=>'javascript:alert(1)'],$config);
verifyRecovery($view['url']===$view['home_url'],'unknown routes safely return home');
$view=formRecoveryView(['SCRIPT_NAME'=>'//attacker.example/../login.php'],$config);
verifyRecovery($view['asset_base']==='/assets/'&&$view['url']==='https://events.example/EventLink/login.php','path tricks cannot create external or traversing recovery URLs');
$_SESSION=['csrf_token'=>'unchanged-session-token'];$_POST=['password'=>'private-password-marker','email'=>'private-email-marker','next'=>'<script>private-script-marker</script>'];
$view=formRecoveryView(['SCRIPT_NAME'=>'/EventLink/login.php'],$config);
$escape=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
ob_start();require __DIR__.'/../includes/form_recovery_page.php';$html=ob_get_clean();
verifyRecovery(!str_contains($html,'private-')&&!str_contains($html,'unchanged-session-token'),'recovery does not reflect submitted data or session tokens');
verifyRecovery($_SESSION['csrf_token']==='unchanged-session-token','rendering does not rotate or invalidate another open form');
verifyRecovery(!str_contains($html,'<script')&&!str_contains($html,'<form'),'recovery is usable without scripts and cannot automatically repost');
verifyRecovery(str_contains($html,'What to do next')&&str_contains($html,'Why this can happen'),'recovery includes next steps and possible causes');
echo "$passed form recovery assertions passed.\n";
