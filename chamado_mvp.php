<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
exigirLogin();

$idChamada = (int) ($_GET['id'] ?? 0);
if ($idChamada <= 0) {
    header('Location: chamados.php');
    exit;
}

$erro = '';
$sucessoMsg = isset($_GET['sucesso']) ? 'Chamado aberto com sucesso!' : '';

// Ações de atendimento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'assumir') {
        $area  = trim($_POST['area'] ?? '');
        $turno = trim($_POST['turno'] ?? '');

        if ($area === '' || $turno === '') {
            $erro = 'Informe a área de atendimento e o turno para assumir o chamado.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO atendente (idChamada, Status_de_Atendimento, Area_de_Atendimento, Turno)
                     VALUES (:id, "Em atendimento", :area, :turno)'
                );
                $stmt->execute(['id' => $idChamada, 'area' => $area, 'turno' => $turno]);

                $stmt = $pdo->prepare('UPDATE chamada SET Status = "Em atendimento" WHERE Id_Chamada = :id');
                $stmt->execute(['id' => $idChamada]);

                registrarHistorico(
                    $pdo,
                    $idChamada,
                    "Chamado assumido por {$_SESSION['nome']} (área: {$area}, turno: {$turno})."
                );

                $pdo->commit();
                header('Location: chamado_detalhes.php?id=' . $idChamada);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao assumir o chamado: ' . $e->getMessage();
            }
        }
    } elseif ($acao === 'finalizar') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE atendente SET Status_de_Atendimento = "Finalizado"
                 WHERE idChamada = :id ORDER BY Id_Atendente DESC LIMIT 1'
            );
            $stmt->execute(['id' => $idChamada]);

            $stmt = $pdo->prepare('UPDATE chamada SET Status = "Finalizado" WHERE Id_Chamada = :id');
            $stmt->execute(['id' => $idChamada]);

            registrarHistorico($pdo, $idChamada, "Chamado finalizado por {$_SESSION['nome']}.");

            $pdo->commit();
            header('Location: chamado_detalhes.php?id=' . $idChamada);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao finalizar o chamado: ' . $e->getMessage();
        }
    }
}

// Busca os dados do chamado
$stmt = $pdo->prepare('SELECT * FROM chamada WHERE Id_Chamada = :id');
$stmt->execute(['id' => $idChamada]);
$chamado = $stmt->fetch();

if (!$chamado) {
    header('Location: chamados.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM usuario WHERE idChamada = :id LIMIT 1');
$stmt->execute(['id' => $idChamada]);
$usuario = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM atendente WHERE idChamada = :id ORDER BY Id_Atendente DESC LIMIT 1');
$stmt->execute(['id' => $idChamada]);
$atendente = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM historico WHERE idChamada = :id ORDER BY Data_Atualizacao ASC');
$stmt->execute(['id' => $idChamada]);
$historico = $stmt->fetchAll();

$tituloPagina = 'Chamado #' . $idChamada;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1>Chamado #<?= (int) $chamado['Id_Chamada'] ?></h1>
    <a href="chamados.php" class="btn btn-outline">← Voltar</a>
</div>

<?php if ($sucessoMsg): ?>
    <div class="alert alert-sucesso"><?= h($sucessoMsg) ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-erro"><?= h($erro) ?></div>
<?php endif; ?>

<div class="details-grid">
    <div class="detail-box">
        <h2>Dados do chamado</h2>
        <p><strong>Horário:</strong> <?= h(date('d/m/Y H:i', strtotime($chamado['Horario']))) ?></p>
        <p><strong>Descrição:</strong><br><?= nl2br(h($chamado['Descricao'])) ?></p>
        <p><strong>Prioridade:</strong> <span class="<?= classePrioridade($chamado['Prioridade']) ?>"><?= h($chamado['Prioridade']) ?></span></p>
        <p><strong>Status:</strong> <span class="<?= classeStatus($chamado['Status']) ?>"><?= h($chamado['Status']) ?></span></p>
    </div>

    <div class="detail-box">
        <h2>Solicitante</h2>
        <?php if ($usuario): ?>
            <p><strong>Nome:</strong> <?= h($usuario['Nome']) ?></p>
            <p><strong>E-mail:</strong> <?= h($usuario['Email']) ?></p>
            <p><strong>Telefone:</strong> <?= h($usuario['Telefone'] ?: '—') ?></p>
            <p><strong>Tipo:</strong> <?= h($usuario['Tipo_Usuario']) ?></p>
        <?php else: ?>
            <p class="text-muted">Nenhum usuário vinculado.</p>
        <?php endif; ?>
    </div>

    <div class="detail-box">
        <h2>Atendente</h2>
        <?php if ($atendente): ?>
            <p><strong>Área:</strong> <?= h($atendente['Area_de_Atendimento']) ?></p>
            <p><strong>Turno:</strong> <?= h($atendente['Turno']) ?></p>
            <p><strong>Status do atendimento:</strong> <?= h($atendente['Status_de_Atendimento']) ?></p>
        <?php else: ?>
            <p class="text-muted">Ainda não atribuído.</p>
        <?php endif; ?>

        <?php if (($_SESSION['tipo'] ?? '') === 'Atendente'): ?>
            <?php if ($chamado['Status'] === 'Aberto'): ?>
                <form method="post" class="form form-inline">
                    <input type="hidden" name="acao" value="assumir">
                    <label for="area">Área de atendimento</label>
                    <input type="text" id="area" name="area" required placeholder="Ex: Suporte Técnico">
                    <label for="turno">Turno</label>
                    <select id="turno" name="turno" required>
                        <option value="">Selecione...</option>
                        <option value="Manhã">Manhã</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noite">Noite</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Assumir chamado</button>
                </form>
            <?php elseif ($chamado['Status'] === 'Em atendimento'): ?>
                <form method="post">
                    <input type="hidden" name="acao" value="finalizar">
                    <button type="submit" class="btn btn-primary">Finalizar chamado</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Este chamado já foi finalizado.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<h2>Histórico</h2>
<?php if (empty($historico)): ?>
    <p class="text-muted">Sem registros de histórico.</p>
<?php else: ?>
    <ul class="timeline">
        <?php foreach ($historico as $h): ?>
            <li>
                <span class="timeline-data"><?= h(date('d/m/Y H:i', strtotime($h['Data_Atualizacao']))) ?></span>
                <span class="timeline-desc"><?= h($h['Descricao']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
