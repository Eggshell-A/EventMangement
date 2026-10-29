<?php
declare(strict_types=1);

/** Staff email IDs are administrator-issued logins, not mailbox ownership claims. */
function lockStaffAdministrator(PDO $pdo): void {
    if (!isRole('admin')) throw new DomainException('Only administrators can manage staff accounts.');
    $q=$pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND role='admin' AND is_active=1 AND auth_version=? FOR UPDATE");
    $q->execute([currentUser()['user_id'],$_SESSION['auth_version']??0]);
    if(!$q->fetchColumn())throw new DomainException('Administrator access changed. Log in again.');
}
function createStaffAccount(PDO $pdo, string $name, string $email): array {
    $email=strtolower(trim($email));$name=trim($name);
    if(!isRole('admin'))throw new DomainException('Only administrators can create staff accounts.');
    if($name===''||!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>100||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid name and login email ID.');
    $password='Staff!9'.bin2hex(random_bytes(12));
    $hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
    $pdo->beginTransaction();
    try{
        lockStaffAdministrator($pdo);
        $q=$pdo->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$email]);
        if($q->fetchColumn())throw new DomainException('This login email ID already exists. No account was changed.');
        $pdo->prepare("INSERT INTO users(name,email,password_hash,role,must_change_password)VALUES(?,?,?,'staff',1)")->execute([$name,$email,$hash]);
        $uid=(int)$pdo->lastInsertId();audit($pdo,'staff.created',$uid);$pdo->commit();
        return ['user_id'=>$uid,'email'=>$email,'temporary_password'=>$password];
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')throw new DomainException('This login email ID already exists. No account was changed.');throw $e;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function resetStaffPassword(PDO $pdo, int $uid): array {
    if(!isRole('admin'))throw new DomainException('Only administrators can reset staff passwords.');
    $password='Staff!9'.bin2hex(random_bytes(12));$hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
    $pdo->beginTransaction();
    try{
        lockStaffAdministrator($pdo);
        $q=$pdo->prepare("SELECT email FROM users WHERE user_id=? AND role='staff' AND is_active=1 AND deleted_at IS NULL FOR UPDATE");$q->execute([$uid]);$email=$q->fetchColumn();
        if(!$email)throw new DomainException('Choose an active staff account.');
        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=1,auth_version=auth_version+1 WHERE user_id=?')->execute([$hash,$uid]);
        $pdo->prepare('DELETE FROM account_tokens WHERE user_id=?')->execute([$uid]);
        // Keep the existing authenticator and recovery hashes; a reset must not bypass MFA.
        audit($pdo,'staff.password_reset',$uid);$pdo->commit();
        return ['user_id'=>$uid,'email'=>$email,'temporary_password'=>$password];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
