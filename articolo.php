<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = (int)($_GET['id'] ?? 0);
$isLogged = isset($_SESSION['user_id']);
require_once __DIR__ . '/includi/seo.php';
require_once __DIR__ . '/includi/db.php';

$baseUrl = seo_base_url();
$articleUrl = $baseUrl . '/articolo.php?id=' . $id;
$articleMeta = [
    'title' => 'Articolo - Tornei Old School',
    'description' => 'Leggi le ultime notizie dei tornei Old School.',
    'url' => $articleUrl,
    'canonical' => $articleUrl,
    'type' => 'article',
    'image' => $baseUrl . '/img/blog/placeholder.jpg',
];
$articleSchema = [];
$breadcrumbSchema = seo_breadcrumb_schema([
    ['name' => 'Home', 'url' => $baseUrl . '/'],
    ['name' => 'Blog', 'url' => $baseUrl . '/blog.php'],
    ['name' => 'Articolo', 'url' => $articleUrl],
]);

if ($id > 0) {
    $stmt = $conn->prepare(
        "SELECT titolo,
                contenuto,
                data_pubblicazione,
                COALESCE(
                    (SELECT CONCAT('/img/blog_media/', file_path)
                     FROM blog_media
                     WHERE post_id = blog_post.id AND tipo = 'image'
                     ORDER BY ordine ASC, id ASC
                     LIMIT 1),
                    CASE
                        WHEN immagine IS NULL OR immagine = '' THEN ''
                        ELSE CONCAT('/img/blog/', immagine)
                    END
                ) AS cover
         FROM blog_post
         WHERE id = ?
         LIMIT 1"
    );

    if ($stmt) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $cover = $row['cover'] ?? '';
                $coverUrl = $cover ? $baseUrl . '/' . ltrim($cover, '/') : $articleMeta['image'];
                $excerpt = seo_trim($row['contenuto'] ?? '', 180);
                $articleMeta = [
                    'title' => ($row['titolo'] ?? 'Articolo') . ' - Tornei Old School',
                    'description' => $excerpt ?: $articleMeta['description'],
                    'url' => $articleUrl,
                    'canonical' => $articleUrl,
                    'type' => 'article',
                    'image' => $coverUrl,
                ];

                $breadcrumbSchema = seo_breadcrumb_schema([
                    ['name' => 'Home', 'url' => $baseUrl . '/'],
                    ['name' => 'Blog', 'url' => $baseUrl . '/blog.php'],
                    ['name' => $row['titolo'] ?? 'Articolo', 'url' => $articleUrl],
                ]);

                $articleSchema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $row['titolo'] ?? '',
                    'description' => $excerpt,
                    'image' => [$coverUrl],
                    'mainEntityOfPage' => $articleUrl,
                    'author' => [
                        '@type' => 'Organization',
                        'name' => 'Tornei Old School',
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'Tornei Old School',
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $baseUrl . '/img/logo_old_school.png',
                        ],
                    ],
                ];

                if (!empty($row['data_pubblicazione'])) {
                    $articleSchema['datePublished'] = date('c', strtotime($row['data_pubblicazione']));
                }
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php render_seo_tags($articleMeta); ?>
<?php render_jsonld($breadcrumbSchema); ?>
<?php render_jsonld($articleSchema); ?>
<link rel="stylesheet" href="<?= asset_url('/style.min.css') ?>">

<style>
.page-background {
    background: radial-gradient(circle at 10% 20%, rgba(31,63,99,0.08) 0%, transparent 25%), radial-gradient(circle at 90% 10%, rgba(231,145,77,0.08) 0%, transparent 22%), linear-gradient(180deg, #f3f6fb 0%, #e8eef7 100%);
    min-height: 100vh;
    padding-bottom: 40px;
}

.article-layout {
    max-width: 1200px;
    margin: 80px auto 60px;
    padding: 0 20px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    column-gap: 30px;
    row-gap: 30px;
}

.article-panel {
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fe 100%);
    border-radius: 20px;
    padding: 42px;
    box-shadow: 0 26px 60px rgba(15, 23, 42, 0.12);
    border: 1px solid #e3e8f4;
}

.article-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    color: #6a738b;
    font-size: 0.95rem;
}

.article-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    background: rgba(255,255,255,0.15);
    padding: 8px 18px;
    border-radius: 999px;
    text-decoration: none;
    transition: background 0.2s;
}

.article-badge:hover {
    background: rgba(255,255,255,0.25);
}

.article-panel h2 {
    font-size: 2.4rem;
    margin: 12px 0 18px;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.article-subtitle {
    color: #54607a;
    font-weight: 600;
    margin: 0 0 14px;
}

.article-media {
    position: relative;
    margin: 28px 0;
    border-radius: 18px;
    overflow: hidden;
    background: #0f172a;
    padding: 12px;
}

.article-media.hidden {
    display: none;
}

.media-stage {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 260px;
}

.media-stage img,
.media-stage video {
    width: 100%;
    height: auto;
    max-height: none;
    object-fit: contain;
    display: block;
    background: #000;
}

@media (min-width: 900px) {
    .media-stage img,
    .media-stage video {
        max-height: 80vh;
    }
}

.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.55);
    border: none;
    color: #fff;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.carousel-nav:hover {
    background: rgba(0,0,0,0.75);
}

.carousel-nav.prev {
    left: 14px;
}

.carousel-nav.next {
    right: 14px;
}

.media-dots {
    position: absolute;
    bottom: 14px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
}

.media-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.45);
    border: none;
    cursor: pointer;
    padding: 0;
}

.media-dot.active {
    background: #fff;
}

.article-content {
    color: #1e2433;
    font-size: 1.1rem;
    line-height: 1.72;
    white-space: pre-wrap;
    font-weight: 400;
}

.article-content p {
    margin: 0 0 18px;
    font-weight: 400;
}

.article-content h2,
.article-content h3 {
    font-weight: 800;
    margin: 18px 0 8px;
}

.article-content strong,
.article-content b { font-weight: 800; }

.article-content *,
.article-content p,
.article-content span,
.article-content li { font-weight: 400; }
.article-content strong,
.article-content b,
.article-content h2,
.article-content h3 { font-weight: 800; }

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    border: 0;
}

.article-sidebar {
    background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    border-radius: 18px;
    padding: 26px;
    box-shadow: 0 20px 46px rgba(15, 23, 42, 0.08);
    border: 1px solid #e4e9f4;
    align-self: flex-start;
    position: sticky;
    top: 140px;
}

.article-sidebar h3 {
    margin-top: 0;
}

.article-backlink-top {
    max-width: 1200px;
    margin: 90px auto 0;
    padding: 0 20px;
}

.btn-back-blog {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #1f3f63, #2a5b8a);
    color: #fff;
    padding: 12px 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 800;
    box-shadow: 0 12px 26px rgba(31,63,99,0.28);
    transition: transform .15s, box-shadow .15s;
}

.btn-back-blog:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(31,63,99,0.32);
}
.article-backlink-inline {
    margin-bottom: 16px;
}

.article-backlink {
    max-width: 1200px;
    margin: 20px auto 0;
    padding: 0 20px;
}

.btn-back-blog {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #1f3f63, #2a5b8a);
    color: #fff;
    padding: 10px 16px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 800;
    box-shadow: 0 12px 26px rgba(31,63,99,0.28);
    transition: transform .15s, box-shadow .15s;
}

.btn-back-blog:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(31,63,99,0.32);
}

.related-post {
    display: block;
    padding: 14px 0;
    border-bottom: 1px solid #e5ecfb;
    text-decoration: none;
    color: inherit;
}

.related-post:last-child {
    border-bottom: none;
}

.related-post span {
    display: block;
    color: #9ca4bb;
    font-size: 0.8rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
}

.related-post strong {
    display: block;
    margin-top: 6px;
    color: #111c2f;
}

.comments-wrapper {
    grid-column: 1 / 2;
    margin: 0 0 40px;
    padding: 0;
}

.comments-card {
    background: #fff;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 25px 50px rgba(15, 23, 42, 0.08);
}

.comments-card h3 {
    margin-top: 0;
}

.comment-item {
    display: flex;
    gap: 14px;
    border-bottom: 1px solid #ecf0fb;
    padding: 16px 0;
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    overflow: hidden;
    background: #e5ebff;
    flex-shrink: 0;
}

.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.comment-body {
    flex: 1;
}

.comment-author {
    font-weight: 700;
    color: #0f172a;
}

.comment-date {
    font-size: 0.82rem;
    color: #9aa3ba;
}

.comment-text {
    margin-top: 8px;
    white-space: pre-wrap;
    color: #1f2533;
}

.comment-form textarea {
    width: 100%;
    border-radius: 14px;
    border: 1px solid #d7def2;
    padding: 12px;
    resize: vertical;
    min-height: 130px;
    font-size: 1rem;
}

.comment-form button {
    background: #1d2f4b;
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 12px 28px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 16px;
    transition: transform 0.2s;
}

.comment-form button:hover {
    transform: translateY(-2px);
}

.reply-info {
    background: #eef2ff;
    border-radius: 12px;
    padding: 10px 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 0.9rem;
}

.reply-cancel {
    border: none;
    background: transparent;
    color: #c2410c;
    font-weight: 600;
    cursor: pointer;
}

.reply-cancel:hover {
    text-decoration: underline;
}

.reply-action {
    border: none;
    background: transparent;
    color: #1d4ed8;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0;
}

.reply-action:hover {
    text-decoration: underline;
}

.delete-action {
    border: none;
    background: transparent;
    color: #b91c1c;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0;
    margin-left: 12px;
}

.delete-action:hover {
    text-decoration: underline;
}

.mention-tag {
    display: inline-block;
    background: #eef2ff;
    color: #1d4ed8;
    border-radius: 999px;
    padding: 2px 10px;
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 6px;
}

.comment-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 6px;
}

.comment-replies {
    margin-top: 12px;
    margin-left: 30px;
    border-left: 2px solid #eef2ff;
    padding-left: 16px;
}

.comment-replies .comment-avatar {
    width: 36px;
    height: 36px;
}

.comment-replies .comment-text {
    font-size: 0.95rem;
}

.comments-hint {
    color: #6d7386;
    font-size: 0.95rem;
}

.feedback-message {
    margin-top: 10px;
    font-size: 0.9rem;
}

.feedback-message.error {
    color: #c2410c;
}

.feedback-message.success {
    color: #15803d;
}

@media (max-width: 1024px) {
    .article-layout {
        grid-template-columns: 1fr;
    }

    .article-panel {
        order: 1;
    }

    .comments-wrapper {
        order: 2;
    }

    .article-sidebar {
        position: static;
        order: 3;
    }
}

@media (max-width: 640px) {
    .article-panel {
        padding: 26px;
    }
}
</style>
</head>

<body class="page-background">

<?php include __DIR__ . '/includi/header.php'; ?>

<main class="article-layout">
  <article class="article-panel" id="articlePanel">
    <div class="article-meta">
      <a class="article-badge" href="/blog.php" aria-label="Torna al blog">
        <span aria-hidden="true">⟵</span> Blog
      </a>
      <span id="articleDate" class="sr-only">--/--/----</span>
    </div>
    <div class="article-backlink-inline">
      <a href="/blog.php" class="btn-back-blog" aria-label="Torna al blog">↩ Torna al blog</a>
    </div>
    <h2 id="articleTitle">Caricamento...</h2>
    <p class="article-subtitle" id="articleSubtitle">Recuperiamo i dettagli e li inquadriamo al meglio.</p>
<div class="article-media hidden" id="articleMedia">
    <button class="carousel-nav prev" id="mediaPrev" aria-label="Media precedente">‹</button>
    <div class="media-stage" id="mediaStage"></div>
    <button class="carousel-nav next" id="mediaNext" aria-label="Media successivo">›</button>
    <div class="media-dots" id="mediaDots"></div>
</div>
    <div class="article-content" id="articleContent">Un attimo di pazienza…</div>
  </article>

  <aside class="article-sidebar">
    <h3>Da leggere dopo</h3>
    <p class="comments-hint">Altri articoli dal nostro staff.</p>
    <div id="relatedList">Caricamento...</div>
  </aside>

  <section class="comments-wrapper">
    <div class="comments-card">
      <h3>Commenti della community</h3>
      <div id="commentsList">Caricamento dei commenti...</div>

      <?php if ($isLogged): ?>
        <form class="comment-form" id="commentForm">
          <div class="reply-info" id="replyInfo" hidden style="display:none;">
            Rispondi a <strong id="replyName"></strong>
            <button type="button" class="reply-cancel" id="replyCancel">Annulla</button>
          </div>
          <label for="commento">Lascia il tuo commento</label>
          <textarea id="commento" name="commento" placeholder="Condividi il tuo punto di vista..." required></textarea>
          <button type="submit">Pubblica</button>
          <div class="feedback-message" id="commentFeedback"></div>
        </form>
      <?php else: ?>
        <p class="comments-hint">
          Vuoi dire la tua? <a href="/login.php">Accedi</a> o <a href="/register.php">registrati</a> per lasciare un commento.
        </p>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includi/footer.html'; ?>

<script>
const articleId = <?= $id ?>;
const isLogged = <?= $isLogged ? 'true' : 'false' ?>;

const articleTitle = document.getElementById('articleTitle');
const articleSubtitle = document.getElementById('articleSubtitle');
const articleDate = document.getElementById('articleDate');
const mediaContainer = document.getElementById('articleMedia');
const mediaStage = document.getElementById('mediaStage');
const mediaDots = document.getElementById('mediaDots');
const mediaPrev = document.getElementById('mediaPrev');
const mediaNext = document.getElementById('mediaNext');
const articleContent = document.getElementById('articleContent');
const relatedList = document.getElementById('relatedList');
const commentsList = document.getElementById('commentsList');
const commentForm = document.getElementById('commentForm');
const commentField = document.getElementById('commento');
const commentFeedback = document.getElementById('commentFeedback');
const replyInfo = document.getElementById('replyInfo');
const replyName = document.getElementById('replyName');
const replyCancelBtn = document.getElementById('replyCancel');
const defaultAvatar = '/img/icone/user.png';
const canReply = <?= $isLogged ? 'true' : 'false' ?>;
let replyTarget = null;
let replyMention = '';

function setFeedback(message, type = '') {
    if (!commentFeedback) {
        return;
    }
    commentFeedback.textContent = message;
    commentFeedback.className = `feedback-message ${type}`.trim();
}

function setReplyTarget(commentId, author) {
    if (!canReply) {
        return;
    }
    replyTarget = commentId;
    replyMention = `@${(author || 'Utente').trim()}`;
    if (replyInfo && replyName) {
        replyName.textContent = author || 'Utente';
        replyInfo.hidden = false;
        replyInfo.style.display = 'inline-flex';
    }
    if (commentField) {
        const mentionRegex = new RegExp(`^${escapeRegex(replyMention)}\\s*`, 'i');
        const withoutMention = commentField.value.replace(mentionRegex, '').trimStart();
        commentField.value = `${replyMention} ${withoutMention}`.trim() + ' ';
        commentField.focus();
        commentField.setSelectionRange(commentField.value.length, commentField.value.length);
    }
}

function resetReplyTarget() {
    const previousMention = replyMention;
    replyTarget = null;
    replyMention = '';
    if (replyInfo) {
        replyInfo.hidden = true;
        replyInfo.style.display = 'none';
    }
    if (commentField && previousMention) {
        const mentionRegex = new RegExp(`^${escapeRegex(previousMention)}\\s*`, 'i');
        commentField.value = commentField.value.replace(mentionRegex, '');
    }
}

function escapeHTML(value = '') {
    return value.replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char] || char));
}

let carouselItems = [];
let currentMediaIndex = 0;
let mediaTitle = '';

function renderMediaCarousel(items = [], fallbackCover = '', title = '') {
    if (!mediaContainer || !mediaStage || !mediaDots) {
        return;
    }
    mediaTitle = title || '';
    carouselItems = Array.isArray(items) ? items.filter(item => item && item.url) : [];
    if (!carouselItems.length && fallbackCover) {
        carouselItems = [{ tipo: 'image', url: fallbackCover }];
    }
    currentMediaIndex = 0;
    if (!carouselItems.length) {
        mediaContainer.classList.add('hidden');
        mediaStage.innerHTML = '';
        mediaDots.innerHTML = '';
        if (mediaPrev) mediaPrev.hidden = true;
        if (mediaNext) mediaNext.hidden = true;
        return;
    }
    mediaContainer.classList.remove('hidden');
    const hasMultiple = carouselItems.length > 1;
    if (mediaPrev) mediaPrev.hidden = !hasMultiple;
    if (mediaNext) mediaNext.hidden = !hasMultiple;
    updateMediaStage();
}

function updateMediaStage() {
    if (!mediaStage) return;
    const current = carouselItems[currentMediaIndex];
    if (!current) {
        mediaStage.innerHTML = '';
        return;
    }
    if (current.tipo === 'video') {
        mediaStage.innerHTML = `<video controls src="${encodeURI(current.url)}"></video>`;
    } else {
        mediaStage.innerHTML = `<img src="${encodeURI(current.url)}" alt="${escapeHTML(mediaTitle)}">`;
    }
    renderMediaDots();
}

function renderMediaDots() {
    if (!mediaDots) return;
    if (carouselItems.length <= 1) {
        mediaDots.innerHTML = '';
        return;
    }
    mediaDots.innerHTML = carouselItems.map((_, idx) => `
        <button class="media-dot${idx === currentMediaIndex ? ' active' : ''}" data-index="${idx}" aria-label="Mostra media ${idx + 1}"></button>
    `).join('');
}

function goToMedia(index) {
    if (!carouselItems.length) return;
    const count = carouselItems.length;
    currentMediaIndex = ((index % count) + count) % count;
    updateMediaStage();
}

function showPrevMedia() {
    goToMedia(currentMediaIndex - 1);
}

function showNextMedia() {
    goToMedia(currentMediaIndex + 1);
}

function escapeRegex(str = '') {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function formatContent(text = '') {
    if (!text) {
        return '<p>Non abbiamo trovato il contenuto di questo articolo.</p>';
    }

    const applyInline = (str) =>
        str
          .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
          .replace(/==(.+?)==/g, '<strong>$1</strong>');

    return text
        .split(/\n{2,}/)
        .map(block => {
            const safe = escapeHTML(block.trim());
            if (safe.startsWith('## ')) {
                return `<h3>${applyInline(safe.slice(3))}</h3>`;
            }
            if (safe.startsWith('# ')) {
                return `<h2>${applyInline(safe.slice(2))}</h2>`;
            }
            return `<p>${applyInline(safe).replace(/\n/g, '<br>')}</p>`;
        })
        .join('');
}

async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();

    try {
        const data = JSON.parse(text);
        return { data, ok: res.ok, status: res.status };
    } catch (error) {
        const clean = text.replace(/<[^>]+>/g, '').trim();
        throw new Error(clean || 'Risposta non valida dal server.');
    }
}

async function loadArticle() {
    try {
        const { data, ok } = await fetchJSON(`/api/blog.php?azione=articolo&id=${articleId}`);
        if (!ok) {
            throw new Error(data?.error || 'Articolo non trovato.');
        }

        document.title = `${data.titolo} - Tornei Old School`;
        articleTitle.textContent = data.titolo;
        articleSubtitle.textContent = 'Pubblicato il ' + data.data;
        articleDate.textContent = data.data;
        renderMediaCarousel(data.media || [], data.cover || '', data.titolo || '');
        articleContent.innerHTML = formatContent(data.contenuto || '');
    } catch (err) {
        articleContent.innerHTML = `<p>${escapeHTML(err.message)}</p>`;
    }
}

async function loadRelated() {
    try {
        const { data: posts } = await fetchJSON('/api/blog.php?azione=ultimi');

        if (!Array.isArray(posts) || !posts.length) {
            relatedList.innerHTML = '<p>Nessun articolo correlato al momento.</p>';
            return;
        }

        relatedList.innerHTML = posts
            .filter(post => Number(post.id) !== articleId)
            .slice(0, 4)
            .map(post => `
                <a class="related-post" href="/articolo.php?id=${post.id}">
                    <span>${escapeHTML(post.data || '')}</span>
                    <strong>${escapeHTML(post.titolo)}</strong>
                </a>
            `).join('');
    } catch (err) {
        relatedList.innerHTML = `<p>${escapeHTML(err.message)}</p>`;
    }
}

function buildCommentHTML(comment, isChild = false, rootId = null, threadAuthor = null) {
    const topId = rootId ?? comment.id;
    const rootAuthor = threadAuthor ?? comment.autore;
    const avatarSrc = comment.avatar
        ? `/${comment.avatar.replace(/^\/+/, '')}`
        : defaultAvatar;
    const replies = Array.isArray(comment.replies) ? comment.replies : [];
    const canReply = !isChild && !!commentForm;
    const actions = [];
    if (canReply) {
        actions.push(`<button class="reply-action" type="button" data-target="${topId}" data-author="${escapeHTML(comment.autore || 'Utente')}">Rispondi</button>`);
    }
    if (comment.can_delete) {
        actions.push(`<button class="delete-action" type="button" data-id="${comment.id}">Elimina</button>`);
    }
    const actionsHtml = actions.length ? `<div class="comment-actions">${actions.join('')}</div>` : '';

    return `
        <div class="comment-item">
            <div class="comment-avatar">
                <img src="${avatarSrc}" alt="${escapeHTML(comment.autore || 'Utente')}">
            </div>
            <div class="comment-body">
                <div class="comment-author">${escapeHTML(comment.autore || 'Utente')}</div>
                <div class="comment-date">${escapeHTML(comment.data || '')}</div>
                ${isChild && rootAuthor ? `<div class="mention-tag">@${escapeHTML(rootAuthor)}</div>` : ''}
                <div class="comment-text">${escapeHTML(comment.commento || '')}</div>
                ${actionsHtml}
                ${replies.length ? `<div class="comment-replies">
                    ${replies.map(reply => buildCommentHTML(reply, true, topId, rootAuthor)).join('')}
                </div>` : ''}
            </div>
        </div>
    `;
}

function renderComments(comments) {
    if (!comments.length) {
        commentsList.innerHTML = '<p class="comments-hint">Ancora nessun commento. Sii il primo a rompere il ghiaccio!</p>';
        return;
    }

    commentsList.innerHTML = comments.map(comment => buildCommentHTML(comment, false, comment.id, comment.autore)).join('');
}

async function fetchComments() {
    try {
        const { data } = await fetchJSON(`/api/blog.php?azione=commenti&id=${articleId}`);
        renderComments(Array.isArray(data) ? data : []);
    } catch (err) {
        commentsList.innerHTML = `<p class="feedback-message error">${escapeHTML(err.message)}</p>`;
    }
}

async function submitComment(event) {
    event.preventDefault();
    if (!commentField) {
        return;
    }

    const text = commentField.value.trim();
    if (!text) {
        setFeedback('Scrivi qualcosa prima di pubblicare.', 'error');
        return;
    }

    setFeedback('Pubblicazione in corso...');

    try {
        const { data, ok } = await fetchJSON('/api/blog.php?azione=commenti_salva', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: articleId, commento: text, parent_id: replyTarget })
        });

        if (!ok || data?.error) {
            throw new Error(data.error || 'Errore inatteso.');
        }

        setFeedback('Commento pubblicato!', 'success');
        commentField.value = '';
        resetReplyTarget();
        fetchComments();
    } catch (err) {
        setFeedback(err.message, 'error');
    }
}

if (isLogged && commentForm) {
    commentForm.addEventListener('submit', submitComment);
}

function showDeleteModal(message, onConfirm) {
    // rimuovi eventuale precedente
    const existing = document.getElementById('commentDeleteModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'commentDeleteModal';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,0.45)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = '9999';

    const dialog = document.createElement('div');
    dialog.style.background = '#fff';
    dialog.style.borderRadius = '12px';
    dialog.style.boxShadow = '0 18px 40px rgba(0,0,0,0.18)';
    dialog.style.padding = '22px';
    dialog.style.maxWidth = '340px';
    dialog.style.width = '92%';
    dialog.style.textAlign = 'center';

    const msg = document.createElement('p');
    msg.textContent = message || 'Eliminare questo commento?';
    msg.style.margin = '0 0 14px';
    msg.style.color = '#15293e';
    msg.style.fontWeight = '700';

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.justifyContent = 'center';
    actions.style.gap = '12px';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Annulla';
    cancelBtn.style.padding = '8px 14px';
    cancelBtn.style.borderRadius = '8px';
    cancelBtn.style.border = '1px solid #c7d1e6';
    cancelBtn.style.background = '#f4f6fb';
    cancelBtn.style.color = '#15293e';
    cancelBtn.style.cursor = 'pointer';
    cancelBtn.style.fontWeight = '700';
    cancelBtn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        overlay.remove();
    });

    const okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.textContent = 'Elimina';
    okBtn.style.padding = '8px 14px';
    okBtn.style.borderRadius = '8px';
    okBtn.style.border = '1px solid #b00000';
    okBtn.style.background = '#d80000';
    okBtn.style.color = '#ffffff';
    okBtn.style.cursor = 'pointer';
    okBtn.style.fontWeight = '700';
    okBtn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        overlay.remove();
        if (typeof onConfirm === 'function') onConfirm();
    });

    actions.appendChild(cancelBtn);
    actions.appendChild(okBtn);
    dialog.appendChild(msg);
    dialog.appendChild(actions);
    overlay.appendChild(dialog);
    overlay.addEventListener('click', (ev) => {
        if (ev.target === overlay) overlay.remove();
    });
    document.body.appendChild(overlay);
}

if (canReply) {
    commentsList?.addEventListener('click', event => {
        const deleteBtn = event.target.closest('.delete-action');
        if (deleteBtn) {
            const commentId = Number(deleteBtn.dataset.id);
            if (!commentId) {
                return;
            }
            showDeleteModal('Eliminare questo commento?', () => {
                fetchJSON('/api/blog.php?azione=commenti_elimina', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: commentId })
                })
                .then(({ data, ok }) => {
                    if (!ok) {
                        throw new Error(data?.error || 'Eliminazione non riuscita.');
                    }
                    fetchComments();
                })
                .catch(err => {
                    setFeedback(err.message, 'error');
                });
            });
            return;
        }

        const btn = event.target.closest('.reply-action');
        if (!btn) {
            return;
        }
        const targetId = Number(btn.dataset.target);
        const author = btn.dataset.author;
        if (targetId > 0) {
            setReplyTarget(targetId, author);
        }
    });

    replyCancelBtn?.addEventListener('click', () => {
        resetReplyTarget();
    });
}

loadArticle();
loadRelated();
fetchComments();

mediaPrev?.addEventListener('click', showPrevMedia);
mediaNext?.addEventListener('click', showNextMedia);
mediaDots?.addEventListener('click', event => {
    const btn = event.target.closest('.media-dot');
    if (!btn) return;
    const index = Number(btn.dataset.index);
    if (!Number.isNaN(index)) {
        goToMedia(index);
    }
});
</script>

</body>
</html>
