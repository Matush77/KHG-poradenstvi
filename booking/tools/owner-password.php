<?php
/* Sets the shared password of the hours page and signs out every device (a new password = everyone signs in again).
   Typed without echo, never as an argument (it would stay in the shell history):
   php booking/tools/owner-password.php
   --generate: makes a random one (4 Czech-keyboard-friendly words) and prints it once. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/booking.php';

if (in_array('--generate', $argv, true)) {
    $words = ['jablko', 'lampa', 'kolo', 'mrak', 'pero', 'okno', 'strom', 'vlak', 'sova', 'hrnek', 'most', 'louka', 'kamen', 'drak',
        'zahrada', 'lodka', 'kniha', 'houba', 'motyl', 'vlna', 'kotva', 'sklo', 'rybnik', 'trava', 'hvezda', 'medved', 'pisek', 'kytara'];
    $pw = implode('-', array_map(fn() => $words[random_int(0, count($words) - 1)], range(1, 4))) . '-' . random_int(10, 99);
} else {
    echo 'Nové heslo (aspoň 10 znaků): ';
    system('stty -echo'); $pw = trim((string)fgets(STDIN)); system('stty echo'); echo "\n";
    echo 'Znovu: ';
    system('stty -echo'); $again = trim((string)fgets(STDIN)); system('stty echo'); echo "\n";
    if ($pw !== $again) exit("Hesla se neshodují.\n");
}
if (strlen($pw) < 10) exit("Heslo je moc krátké.\n");
$file = cfg()['dir'] . '/owner-password';
file_put_contents($file, password_hash($pw, PASSWORD_DEFAULT) . "\n");
chmod($file, 0600);
$n = db()->exec('UPDATE owner_devices SET revoked_at = ' . time() . ' WHERE revoked_at IS NULL');
log_line('OWNER_PASSWORD_SET', ['signed_out' => $n]);
echo "Heslo nastaveno, odhlášeno zařízení: $n.\n";
if (in_array('--generate', $argv, true)) echo "Heslo: $pw\n";
