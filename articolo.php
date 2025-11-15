<?php $id = (int)($_GET['id'] ?? 0); ?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Articolo</title>
<link rel="stylesheet" href="/torneioldschool/style.css">

<style>
.article-container {
    max-width:900px;
    margin:40px auto;
    padding:20px;
}
.article-container img {
    width:100%;
    max-height:400px;
    object-fit:cover;
    border-radius:10px;
}
.article-container h1 { font-size:32px; margin-top:20px; }
.article-date { margin:12px 0; color:#777; }
.article-content { margin-top:20px; font-size:18px; line-height:1.6; }
</style>
</head>

<body>

<?php include __DIR__ . '/includi/header.php'; ?>

<div class="article-container" id="articleBox">Caricamento...</div>

<script>
async function loadArticle() {
    const r = await fetch('/torneioldschool/api/blog.php?azione=articolo&id=<?= $id ?>');
    const a = await r.json();

    document.getElementById("articleBox").innerHTML = `
        <img src="/torneioldschool/img/blog/${a.immagine}">
        <h1>${a.titolo}</h1>
        <div class="article-date">${a.data}</div>
        <div class="article-content">${a.contenuto.replace(/\n/g,'<br>')}</div>
    `;
}

loadArticle();
</script>

</body>
</html>
