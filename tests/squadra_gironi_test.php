<?php
require_once __DIR__ . '/../api/crud/torneo.php';
require_once __DIR__ . '/../api/crud/Squadra.php';
require_once __DIR__ . '/../includi/squadra_gironi.php';

function expect_girone($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

class GironiRows {
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch_assoc() { return array_shift($this->rows); }
}

class GironiTorneoFixture extends Torneo {
    public $rows = [];
    public function __construct() {}
    public function getAll() { return new GironiRows($this->rows); }
}

$torneo = new GironiTorneoFixture();
foreach (['Coppa.php', 'Coppa.html', 'Coppa.htm', 'Coppa', '/tornei/Coppa.php'] as $file) {
    $torneo->rows = [['filetorneo' => $file, 'config' => json_encode(['formato' => 'girone', 'numero_gironi' => 2])]];
    $info = getGironeInfoForTorneo($torneo, sanitizeTorneoSlugValue($file));
    expect_girone($info === ['is_girone' => true, 'labels' => ['A', 'B']], 'Gironi riconosciuti per il valore del menu: ' . $file);
}
expect_girone(!getGironeInfoForTorneo($torneo, 'inesistente')['is_girone'], 'Torneo inesistente');
$torneo->rows = [['filetorneo' => 'Campionato.php', 'config' => ['formato' => 'campionato', 'numero_gironi' => 2]]];
expect_girone(!getGironeInfoForTorneo($torneo, 'Campionato')['is_girone'], 'Campionato senza gironi');

// Isola il modello dal DB reale e controlla i valori trasmessi al salvataggio.
class GironiStatement {
    public $values = [];
    public function bind_param($types, &...$values) { $this->values = $values; }
    public function execute() { return true; }
}
class GironiConnection {
    public $sql;
    public $statement;
    public function prepare($sql) {
        $this->sql = $sql;
        return $this->statement = new GironiStatement();
    }
}
$reflection = new ReflectionClass(Squadra::class);
$squadra = $reflection->newInstanceWithoutConstructor();
$connection = new GironiConnection();
foreach (['conn' => $connection, 'hasGirone' => true] as $name => $value) {
    $property = $reflection->getProperty($name);
    $property->setAccessible(true);
    $property->setValue($squadra, $value);
}
expect_girone($squadra->crea('Squadra test', 'Coppa', null, 'Girone B'), 'Creazione riuscita');
expect_girone(strpos($connection->sql, 'girone') !== false && $connection->statement->values === ['Squadra test', 'Coppa', 'B', null], 'Girone scelto incluso nei dati salvati');
$property = $reflection->getProperty('hasGirone');
$property->setAccessible(true);
$property->setValue($squadra, false);
foreach (['crea', 'aggiorna'] as $action) {
    $connection->sql = null;
    try {
        if ($action === 'crea') $squadra->crea('Test', 'Coppa', null, 'B');
        else $squadra->aggiorna(1, 'Test', 'Coppa', 0, 0, 0, 0, 0, 0, 0, 0, null, 'B');
        throw new LogicException('Girone scartato senza errore');
    } catch (RuntimeException $e) {
        expect_girone(strpos($e->getMessage(), 'squadre.girone') !== false, 'Errore esplicito sulla colonna mancante');
        expect_girone($connection->sql === null, 'Nessuna scrittura incompleta');
    }
}
expect_girone($squadra->crea('Test', 'Campionato'), 'Creazione senza girone ancora consentita');
echo "OK: riconoscimento tornei, salvataggio girone scelto e colonna mancante.\n";
