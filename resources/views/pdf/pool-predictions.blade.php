<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 22px 26px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #20242c; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .brand-bar { height: 8px; background: #f5a623; margin: -22px -26px 14px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .app-name { color: #f5a623; font-size: 19px; font-weight: bold; line-height: 1; }
        .document-name { color: #20242c; font-size: 12px; font-weight: bold; margin-top: 4px; text-transform: uppercase; }
        .meta { color: #6d7480; font-size: 8px; text-align: right; line-height: 1.6; }
        .pool-title { margin: 0 0 3px; font-size: 20px; color: #12151a; }
        .competition { margin-bottom: 12px; color: #727985; font-size: 9px; text-transform: uppercase; letter-spacing: .7px; }
        .panel { border: 1px solid #dfe3e8; border-radius: 5px; margin-bottom: 10px; padding: 9px 11px; }
        .panel-title { color: #8b5a08; font-size: 9px; font-weight: bold; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .5px; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { width: 50%; vertical-align: top; padding: 2px 8px 2px 0; }
        .label { color: #7b828d; font-size: 7px; text-transform: uppercase; }
        .value { color: #20242c; font-size: 10px; font-weight: bold; }
        .ranking-box { background: #20242c; color: #fff; border-radius: 5px; padding: 8px 10px; }
        .ranking-box td { color: #fff; }
        .ranking-number { color: #f5a623; font-size: 18px; font-weight: bold; }
        ul { margin: 3px 0 0 14px; padding: 0; }
        li { margin-bottom: 3px; }
        .predictions { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .predictions thead { display: table-header-group; }
        .predictions caption { color: #8b5a08; font-size: 9px; font-weight: bold; padding: 3px 0 6px; text-align: left; text-transform: uppercase; letter-spacing: .5px; }
        .predictions th { background: #20242c; color: #fff; padding: 5px 4px; font-size: 7px; text-transform: uppercase; }
        .predictions td { border-bottom: 1px solid #e4e7eb; padding: 4px; vertical-align: middle; }
        .predictions tr:nth-child(even) td { background: #f7f8fa; }
        .date-col { width: 12%; }
        .match-col { width: 47%; }
        .score-col { width: 14%; text-align: center; font-weight: bold; }
        .points-col { width: 10%; text-align: center; font-weight: bold; }
        .team { white-space: nowrap; }
        .versus { color: #8a9099; padding: 0 4px; }
        .match-meta { color: #7b828d; font-size: 7px; margin-bottom: 3px; }
        .pending { color: #5e6570; font-family: DejaVu Sans Mono, monospace; }
        .empty { border: 1px dashed #c9ced5; color: #727985; padding: 18px; text-align: center; }
        .footer { position: fixed; left: 0; right: 0; bottom: -22px; color: #8a9099; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
    <div class="brand-bar"></div>

    <table class="header">
        <tr>
            <td style="width: 70px;">
                @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $app_name }}">@endif
            </td>
            <td>
                <div class="app-name">{{ $app_name }}</div>
                <div class="document-name">Extrato oficial de palpites</div>
            </td>
            <td class="meta">
                Emitido em {{ $generated_at->format('d/m/Y \à\s H:i') }}<br>
                Horário de Brasília<br>
                Documento pessoal do participante
            </td>
        </tr>
    </table>

    <h1 class="pool-title">{{ $pool->name }}</h1>
    <div class="competition">
        {{ $pool->competition?->name ?? 'Competição' }}
        @if($pool->season?->year) · Temporada {{ $pool->season->year }} @endif
    </div>

    <div class="panel ranking-box">
        <table class="info-grid">
            <tr>
                <td>
                    <div class="label" style="color:#b9bec6">Participante</div>
                    <div class="value" style="color:#fff;font-size:14px">{{ $participant['name'] }}</div>
                </td>
                <td style="text-align:right">
                    <span class="ranking-number">{{ $participant['position'] ? '#'.$participant['position'] : '—' }}</span>
                    <span style="color:#b9bec6"> posição</span>
                    <span style="padding:0 8px;color:#6f7680">|</span>
                    <span class="ranking-number">{{ $participant['points'] }}</span>
                    <span style="color:#b9bec6"> pontos</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <div class="panel-title">Organização do bolão</div>
        <table class="info-grid">
            @foreach($leaders->chunk(2) as $leaderPair)
            <tr>
                @foreach($leaderPair as $leader)
                <td>
                    <div class="label">{{ $leader['role'] }}</div>
                    <div class="value">{{ $leader['name'] }}</div>
                </td>
                @endforeach
                @if($leaderPair->count() === 1)<td></td>@endif
            </tr>
            @endforeach
        </table>
    </div>

    <div class="panel">
        <div class="panel-title">Regras de pontuação</div>
        <ul>
            @foreach($rules as $rule)<li>{{ $rule }}</li>@endforeach
        </ul>
        @if($tie_breakers->isNotEmpty())
            <div class="label" style="margin-top:7px">Critérios de desempate, em ordem</div>
            <div>{{ $tie_breakers->map(fn ($item, $index) => ($index + 1).'. '.$item)->implode(' · ') }}</div>
        @endif
        @if($pool->instructions)
            <div class="label" style="margin-top:7px">Regulamento do organizador</div>
            <div style="white-space:pre-line">{{ $pool->instructions }}</div>
        @endif
    </div>

    @if($matches->isEmpty())
        <div class="panel-title" style="margin-top:13px">Tabela de jogos e palpites</div>
        <div class="empty">Nenhum jogo encontrado para este bolão.</div>
    @else
        <table class="predictions">
            <caption>Tabela de jogos e palpites</caption>
            <thead>
                <tr>
                    <th class="date-col">Data</th>
                    <th class="match-col">Partida</th>
                    <th class="score-col">Palpite</th>
                    <th class="score-col">Placar</th>
                    <th class="points-col">Pontos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matches as $row)
                <tr>
                    <td class="date-col">{{ $row['date'] }}</td>
                    <td class="match-col">
                        <div class="match-meta">{{ $row['stage'] }}{{ $row['round'] ? ' · '.$row['round'] : '' }}</div>
                        <span class="team">{{ $row['home_team'] }}</span>
                        <span class="versus">x</span>
                        <span class="team">{{ $row['away_team'] }}</span>
                    </td>
                    <td class="score-col">{{ $row['prediction'] }}</td>
                    <td class="score-col {{ $row['finished'] ? '' : 'pending' }}">{{ $row['result'] }}</td>
                    <td class="points-col {{ $row['finished'] ? '' : 'pending' }}">{{ $row['points'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ $app_name }} · {{ $pool->name }} · {{ $participant['name'] }} · Emitido em {{ $generated_at->format('d/m/Y H:i') }}
    </div>
</body>
</html>
