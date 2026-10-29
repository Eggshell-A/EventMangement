<?php
declare(strict_types=1);
function inviteStaff(PDO $pdo, string $name, string $email): bool {
    global $config;
    if (!isRole('admin')) throw new DomainException('Only admins can invite staff.');
    if ($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name)>100 || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid name and email.');
    $q=$pdo->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$email]);
    if($q->fetchColumn())throw new DomainException('An account already uses this email. Use a separate staff email.');
    $raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);
    $pdo->prepare('INSERT INTO staff_invites(email,name,token_hash,invited_by,expires_at)VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE name=VALUES(name),token_hash=VALUES(token_hash),invited_by=VALUES(invited_by),expires_at=VALUES(expires_at)')->execute([$email,$name,$hash,currentUser()['user_id']]);
    $url=rtrim($config['base_url'],'/').'/accept_staff.php?token='.$raw;
    if(!sendAccountEmail($email,'Your Fairboard staff invitation',"An administrator invited you to Fairboard. Set your own password and authenticator here:\n$url\nThis one-use invitation expires in 24 hours. Ignore it if unexpected.")){
        $pdo->prepare('DELETE FROM staff_invites WHERE token_hash=?')->execute([$hash]);return false;
    }
    audit($pdo,'staff.invited');return true;
}
function acceptStaffInvite(PDO $pdo, string $raw, string $password): bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$raw)||!passwordMeetsPolicy($password))return false;
    $passwordHash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT i.* FROM staff_invites i JOIN users u ON u.user_id=i.invited_by WHERE token_hash=? AND expires_at>NOW() AND u.is_active=1 AND u.role=\'admin\' FOR UPDATE');$q->execute([hash('sha256',$raw)]);$invite=$q->fetch();
        if(!$invite){$pdo->rollBack();return false;}
        $q=$pdo->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$invite['email']]);
        if($q->fetchColumn()){$pdo->rollBack();return false;}
        $pdo->prepare("INSERT INTO users(name,email,password_hash,role,email_verified_at)VALUES(?,?,?,'staff',NOW())")->execute([$invite['name'],$invite['email'],$passwordHash]);
        $uid=(int)$pdo->lastInsertId();$pdo->prepare('DELETE FROM staff_invites WHERE invite_id=?')->execute([$invite['invite_id']]);audit($pdo,'staff.accepted',$uid);$pdo->commit();return true;
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')return false;throw $e;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
