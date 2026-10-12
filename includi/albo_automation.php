<?php
/** Deterministic award proposals. This module never writes to the database. */
function albo_key(string $value): string {
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii === false ? $value : $ascii)) ?? '');
}

function albo_slug(string $value): string {
    $path = parse_url($value, PHP_URL_PATH) ?: $value;
    return strtolower(preg_replace('/\.(php|html?)$/i', '', basename($path)) ?? '');
}

function albo_kind(string $label): string {
    $key = albo_key($label);
    foreach (['gold'=>'GOLD','silver'=>'SILVER','bronzo'=>'BRONZO','bronze'=>'BRONZO','regular'=>'REGULAR'] as $word=>$kind) {
        if (str_contains($key, $word)) return $kind;
    }
    if (preg_match('/capocannon|miglior marcator/', $key)) return 'SCORER';
    foreach (['portiere'=>'KEEPER','difensore'=>'DEFENDER','attaccante'=>'ATTACKER','giocatore'=>'MVP'] as $word=>$kind) {
        if (str_contains($key, $word)) return $kind;
    }
    if ($key === 'mvp') return 'MVP';
    if (in_array($key, ['vincente','vincitore','vincitrice','vincente torneo'], true)) return 'GOLD';
    return 'CUSTOM:' . $key;
}

function albo_config(array $tournament): array {
    $config = $tournament['config'] ?? [];
    return is_array($config) ? $config : (json_decode((string)$config, true) ?: []);
}

function albo_phase(array $match): string {
    $phase = strtoupper(trim((string)($match['fase'] ?? '')));
    return in_array($phase, ['', 'GIRONE'], true) ? 'REGULAR' : $phase;
}

function albo_matches(array $rows): array {
    if (array_is_list($rows)) return $rows;
    $result = [];
    foreach ($rows as $group) if (is_array($group)) foreach ($group as $match) if (is_array($match)) $result[] = $match;
    return $result;
}

function albo_score_valid(array $match): bool {
    return (int)($match['giocata'] ?? 0) === 1
        && isset($match['gol_casa'], $match['gol_ospite'])
        && is_numeric($match['gol_casa']) && is_numeric($match['gol_ospite'])
        && (int)$match['gol_casa'] >= 0 && (int)$match['gol_ospite'] >= 0;
}

/** Only a complete final can award a cup; alphabetical order never decides ties. */
function albo_final_winner(array $finals): array {
    if (!$finals) return ['', 'Finale non disponibile.'];
    $pairs = [];
    $legs = [];
    $goals = [];
    foreach ($finals as $match) {
        if (!albo_score_valid($match)) return ['', 'Finale non finalizzata o risultato mancante.'];
        $pair = [albo_key((string)$match['squadra_casa']), albo_key((string)$match['squadra_ospite'])];
        sort($pair);
        $pairs[implode('|', $pair)] = true;
        $leg = strtoupper(trim((string)($match['fase_leg'] ?? '')));
        $legs[] = $leg;
        foreach (['casa','ospite'] as $side) {
            $name = (string)$match['squadra_' . $side];
            $goals[$name] = ($goals[$name] ?? 0) + (int)$match['gol_' . $side];
        }
    }
    if (count($pairs) !== 1 || count($goals) !== 2) return ['', 'Finali multiple o squadre incoerenti: verifica manuale.'];
    if (count($finals) === 1 && in_array($legs[0], ['ANDATA','RITORNO'], true)) return ['', 'Finale andata/ritorno incompleta.'];
    if (count($finals) === 2) {
        sort($legs);
        if ($legs !== ['ANDATA','RITORNO']) return ['', 'Tipo gara della finale ambiguo.'];
    } elseif (count($finals) !== 1) return ['', 'Numero di gare della finale non valido.'];
    arsort($goals);
    $names = array_keys($goals); $values = array_values($goals);
    if ($values[0] !== $values[1]) return [$names[0], 'Risultato finale' . (count($finals) === 2 ? ' aggregato' : '')];
    $decisive = count($finals) === 1 ? $finals[0] : array_values(array_filter($finals, static fn($m) => strtoupper((string)$m['fase_leg']) === 'RITORNO'))[0];
    if ((int)($decisive['decisa_rigori'] ?? 0) !== 1 || !isset($decisive['rigori_casa'], $decisive['rigori_ospite'])
        || (int)$decisive['rigori_casa'] < 0 || (int)$decisive['rigori_ospite'] < 0
        || (int)$decisive['rigori_casa'] === (int)$decisive['rigori_ospite']) return ['', 'Pareggio senza esito valido ai rigori.'];
    return [(string)$decisive[(int)$decisive['rigori_casa'] > (int)$decisive['rigori_ospite'] ? 'squadra_casa' : 'squadra_ospite'], 'Finale decisa ai rigori'];
}

function albo_regular_winner(array $teams, array $matches): array {
    $regular = array_values(array_filter($matches, static fn($m) => albo_phase($m) === 'REGULAR'));
    if (!$regular || !$teams) return ['', 'Classifica Regular Season non disponibile.'];
    foreach ($regular as $match) if (!albo_score_valid($match)) return ['', 'Regular Season con risultati non finalizzati.'];
    $top = max(array_map(static fn($t) => (int)($t['punti'] ?? 0), $teams));
    $tied = array_values(array_filter($teams, static fn($t) => (int)($t['punti'] ?? 0) === $top && (int)($t['giocate'] ?? 0) > 0));
    if (!$tied) return ['', 'Nessuna squadra con una classifica valida.'];
    if (count($tied) === 2) {
        $names = array_column($tied, 'nome');
        $playoff = array_values(array_filter($matches, static fn($m) => albo_phase($m) === 'SPAREGGIO'
            && in_array($m['squadra_casa'], $names, true) && in_array($m['squadra_ospite'], $names, true)));
        if ($playoff) return albo_final_winner($playoff);
        $head = array_fill_keys($names, 0);
        foreach ($regular as $match) {
            if (!isset($head[$match['squadra_casa']], $head[$match['squadra_ospite']])) continue;
            $h = (int)$match['gol_casa']; $a = (int)$match['gol_ospite'];
            if ($h === $a) { $head[$match['squadra_casa']]++; $head[$match['squadra_ospite']]++; }
            else $head[$h > $a ? $match['squadra_casa'] : $match['squadra_ospite']] += 3;
        }
        if ($head[$names[0]] !== $head[$names[1]]) return [$head[$names[0]] > $head[$names[1]] ? $names[0] : $names[1], 'Scontri diretti'];
    }
    usort($tied, static fn($a,$b) => ((int)$b['differenza_reti'] <=> (int)$a['differenza_reti']) ?: ((int)$b['gol_fatti'] <=> (int)$a['gol_fatti']));
    if (count($tied) > 1 && (int)$tied[0]['differenza_reti'] === (int)$tied[1]['differenza_reti'] && (int)$tied[0]['gol_fatti'] === (int)$tied[1]['gol_fatti']) return ['', 'Parita non risolta dai criteri sportivi: scelta admin necessaria.'];
    return [(string)$tied[0]['nome'], 'Classifica definitiva della Regular Season'];
}

function albo_award(array $tournament, string $kind, array $winner, string $reason, string $type = 'Squadra'): array {
    $single = !empty($tournament['data_inizio']) && $tournament['data_inizio'] === ($tournament['data_fine'] ?? '');
    $labels = ['REGULAR'=>'Vincente Regular Season','GOLD'=>'Vincente Coppa Gold','SILVER'=>'Vincente Coppa Silver','BRONZO'=>'Vincente Coppa Bronzo',
        'SCORER'=>'Capocannoniere','MVP'=>'Miglior giocatore','KEEPER'=>'Miglior portiere','DEFENDER'=>'Miglior difensore','ATTACKER'=>'Miglior attaccante'];
    $id = (int)($winner['id'] ?? 0);
    $key = ($tournament['sezione'] ?? 'calcio') . ':' . (int)$tournament['id'] . ':' . substr((string)($tournament['data_fine'] ?? ''),0,4) . ':' . $kind . ($kind === 'SCORER' ? ':' . $id : '');
    $row = ['source_key'=>$key,'torneo_id'=>(int)$tournament['id'],'kind'=>$kind,'tipo'=>$type,
        'competizione'=>(string)$tournament['nome'],'sezione'=>$tournament['sezione'] ?? 'calcio',
        'premio'=>($single && $kind === 'GOLD') ? 'Vincente' : ($labels[$kind] ?? $kind),
        'vincitrice'=>$type === 'Squadra' ? (string)$winner['nome'] : trim((string)$winner['nome'] . ' ' . (string)($winner['cognome'] ?? '')),
        'squadra_id'=>$type === 'Squadra' ? $id : (int)($winner['squadra_id'] ?? 0), 'giocatore_id'=>$type === 'Individuale' ? $id : null,
        'logo_vincitrice'=>$type === 'Squadra' ? ($winner['logo'] ?? '') : ($winner['foto'] ?? ''),
        'torneo_logo'=>$tournament['img'] ?? '', 'link_torneo'=>'/tornei/' . basename((string)$tournament['filetorneo']),
        'data_inizio'=>$tournament['data_inizio'] ?? '', 'data_fine'=>$tournament['data_fine'] ?? '',
        'anno'=>substr((string)($tournament['data_fine'] ?? ''),0,4),'giornata_unica'=>$single ? 1 : 0,
        'data_evento'=>$single ? ($tournament['data_fine'] ?? '') : '', 'reason'=>$reason,
        'gol'=>$kind === 'SCORER' ? (int)($winner['gol'] ?? 0) : null];
    $row['source_hash'] = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $row;
}

function albo_propose(array $tournament, array $matches, array $teams, array $scorers, bool $closing = false): array {
    $result = ['tournament'=>$tournament,'awards'=>[],'issues'=>[],'blocking'=>[]];
    if (($tournament['stato'] ?? '') !== 'terminato' && !$closing) return $result;
    $matches = albo_matches($matches); $config = albo_config($tournament);
    $lookup = []; foreach ($teams as $team) $lookup[albo_key((string)$team['nome'])] = $team;
    $single = !empty($tournament['data_inizio']) && $tournament['data_inizio'] === ($tournament['data_fine'] ?? '');
    $grouped = (int)($config['numero_gironi'] ?? 0) > 1 || strtolower((string)($config['formato'] ?? '')) === 'girone';
    if (!$single && !$grouped) {
        [$winner,$reason] = albo_regular_winner($teams,$matches);
        if ($winner !== '' && isset($lookup[albo_key($winner)])) $result['awards'][] = albo_award($tournament,'REGULAR',$lookup[albo_key($winner)],$reason);
        else $result['issues'][] = 'Regular Season: ' . $reason;
    }
    foreach (['GOLD','SILVER','BRONZO'] as $phase) {
        $phaseMatches = array_values(array_filter($matches, static fn($m) => albo_phase($m) === $phase));
        $required=(int)($config['qualificati_' . strtolower($phase)] ?? 0) > 0
            || ($phase === 'GOLD' && in_array(strtolower((string)($config['fase_finale'] ?? '')),['coppe','eliminazione_diretta'],true))
            || preg_match('/coppa\s+' . $phase . '/i',(string)($config['regole_html'] ?? ''));
        if (!$phaseMatches && !$required) continue;
        $finals = array_values(array_filter($phaseMatches, static fn($m) => strtoupper(trim((string)($m['fase_round'] ?? ''))) === 'FINALE'
            || (empty($m['fase_round']) && (int)($m['giornata'] ?? 0) === 1)));
        [$winner,$reason] = albo_final_winner($finals);
        if ($winner !== '' && isset($lookup[albo_key($winner)])) $result['awards'][] = albo_award($tournament,$phase,$lookup[albo_key($winner)],$reason);
        else { $result['issues'][] = $phase . ': ' . ($winner !== '' ? 'Squadra vincitrice non identificabile.' : $reason); $result['blocking'][] = end($result['issues']); }
    }
    $complete = $matches !== [] && count(array_filter($matches,'albo_score_valid')) === count($matches);
    if (!$complete) $result['blocking'][] = 'Alcune partite non sono finalizzate o mancano risultati.';
    if ($complete && $scorers) {
        $goals = max(array_map(static fn($s) => (int)($s['gol'] ?? 0),$scorers)); $seen=[];
        foreach ($scorers as $scorer) {
            if ($goals <= 0 || (int)($scorer['gol'] ?? 0) !== $goals || isset($seen[(int)$scorer['id']])) continue;
            $seen[(int)$scorer['id']] = true;
            $team = $lookup[albo_key((string)($scorer['squadra'] ?? ''))] ?? [];
            $scorer['squadra_id'] = (int)($team['id'] ?? 0);
            $result['awards'][] = albo_award($tournament,'SCORER',$scorer,'Classifica marcatori del sito, ex aequo inclusi','Individuale');
        }
    } else $result['issues'][] = 'Capocannoniere: classifica assente o torneo con risultati non finalizzati.';
    return $result;
}
