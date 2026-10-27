<?php
// Exercise the actual notifier with an unconnected DB: corrections must return
// before even checking notification schema or attempting delivery.
$source = file_get_contents(__DIR__ . '/../api/partita_giocatore.php');
$start = strpos($source, 'function inviaNotificaEsito(');
$end = strpos($source, '// assicura setup', $start);
if ($start === false || $end === false) throw new RuntimeException('Notifier not found');
eval(substr($source, $start, $end - $start));
$conn = new mysqli();
inviaNotificaEsito($conn, 42, ['old_giocata' => 1]);
inviaNotificaEsito($conn, 42, ['old_giocata' => '1']);
echo "Played report corrections skip notification schema and delivery.\n";
