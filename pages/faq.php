<?php
// ===== pages/faq.php =====
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->query("SELECT * FROM faq WHERE ativo = 1 ORDER BY ordem, categoria");
$faqs = $stmt->fetchAll();

// Agrupar por categoria
$faq_por_categoria = [];
foreach ($faqs as $faq) {
    $cat = $faq['categoria'];
    if (!isset($faq_por_categoria[$cat])) {
        $faq_por_categoria[$cat] = [];
    }
    $faq_por_categoria[$cat][] = $faq;
}

$page_title = 'FAQ - Perguntas Frequentes - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.faq-page {
    padding: 30px 0;
}

.faq-category {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.faq-category h2 {
    font-size: 24px;
    margin-bottom: 25px;
    color: var(--accent);
}

.faq-item {
    background: var(--bg-primary);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.faq-item:hover {
    transform: translateX(5px);
}

.faq-question {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.faq-answer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
    line-height: 1.6;
    color: var(--text-secondary);
    display: none;
}

.faq-item.active .faq-answer {
    display: block;
}

.faq-item.active .fa-chevron-down {
    transform: rotate(180deg);
}
</style>

<div class="container">
    <div class="faq-page">
        <div class="page-header" style="text-align: center;">
            <h1 class="page-title">
                <i class="fas fa-question-circle"></i> Perguntas Frequentes
            </h1>
            <p class="page-subtitle">Encontre respostas para suas dúvidas</p>
        </div>
        
        <?php foreach ($faq_por_categoria as $categoria => $items): ?>
        <div class="faq-category">
            <h2><i class="fas fa-folder"></i> <?php echo sanitize($categoria); ?></h2>
            
            <?php foreach ($items as $faq): ?>
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span><?php echo sanitize($faq['pergunta']); ?></span>
                    <i class="fas fa-chevron-down" style="transition: transform 0.3s;"></i>
                </div>
                <div class="faq-answer">
                    <?php echo nl2br(sanitize($faq['resposta'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; padding: 40px 20px; background: var(--bg-secondary); border-radius: 15px;">
            <i class="fas fa-headset" style="font-size: 48px; color: var(--accent); margin-bottom: 20px;"></i>
            <h2 style="margin-bottom: 15px;">Não encontrou sua resposta?</h2>
            <p style="color: var(--text-secondary); margin-bottom: 25px;">
                Entre em contato com nossa equipe de suporte
            </p>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-envelope"></i> Fale Conosco
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>