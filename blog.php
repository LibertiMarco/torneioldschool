<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blog - Ultime Notizie</title>
<link rel="stylesheet" href="/torneioldschool/style.css">

<style>
.blog-header { text-align:center; margin:40px 0 20px; }
.blog-header h1 { font-size:36px; font-weight:800; text-transform:uppercase; }

.blog-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(260px,1fr));
    gap:25px;
    padding:20px;
}

.blog-card {
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
    overflow:hidden;
    cursor:pointer;
    transition:transform .2s;
}
.blog-card:hover { transform:translateY(-5px); }

.blog-card img {
    width:100%;
    height:170px;
    object-fit:cover;
}

.blog-card-content { padding:15px; }
.blog-card h3 { margin:0; font-size:20px; font-weight:700; }
.blog-card p { margin-top:10px; color:#555; }
.blog-date { margin-top:8px; font-size:14px; color:#999; }
</style>
</head>

<body>

<?php include __DIR__ . '/includi/header.php'; ?>

<div class="blog-header">
  <h1>Blog - Ultime Notizie</h1>
</div>

<section class="blog-grid" id="blogGrid"></section>

<script>
async function loadBlog() {
    const r = await fetch('/torneioldschool/api/blog.php?azione=lista');
    const posts = await r.json();
    const grid = document.getElementById("blogGrid");

    grid.innerHTML = "";

    posts.forEach(p => {
        grid.innerHTML += `
        <div class="blog-card" onclick="location.href='articolo.php?id=${p.id}'">
            <img src="/torneioldschool/img/blog/${p.immagine}">
            <div class="blog-card-content">
                <h3>${p.titolo}</h3>
                <div class="blog-date">${p.data}</div>
            </div>
        </div>`;
    });
}

loadBlog();
</script>

</body>
</html>
