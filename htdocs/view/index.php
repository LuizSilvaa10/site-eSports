<?php

// Inclui o arquivo de configuração global do aplicativo:
require($_SERVER['DOCUMENT_ROOT'] . '/_config.php');

// Define o título desta página:
$page_title = 'Artigo Completo';

// Define o conteúdo principal desta página:
$page_article = "<h2>{$page_title}</h2>";

// Define o conteúdo da barra lateral desta página:
$page_aside = '<h3>Barra lateral</h3>';

/***********************************************
 * Todo o código PHP desta página começa aqui! *
 ***********************************************/

// Obtém o ID do artigo a ser visualizado, da URL da página:
$id = intval($_SERVER['QUERY_STRING']);

// Se o ID retornado é '0', redireciona para a página inicial:
if ($id == 0) header('Location: /');

// Cria a query para obter o artigo completo do banco de dados:
$sql = <<<SQL

SELECT *, DATE_FORMAT(art_date, '%d/%m/%Y às %H:%i') AS datebr FROM articles
INNER JOIN users ON art_author = user_id
WHERE art_id = '{$id}'
	AND art_status = 'on'
    AND art_date <= NOW();

SQL;

// Executa a query e armazena em $res:
$res = $conn->query($sql);

// Se não obteve um artigo com esses requisitos, volta para 'index':
// O atributo 'num_rows' mostra quantos registro vieram do DB.
if ($res->num_rows != 1) header('Location: /');

// Converte dados obtidos para array e armazena em $art:
$art = $res->fetch_assoc();

// Título do artigo como título da página:
$page_title = $art['art_title'];

/**
 * Formata nome do autor e data da postagem:
 */
// Obtém o nome do autor em partes:
$parts = explode(' ', $art['user_name']);
$autor_name = $parts[0] . " " . $parts[count($parts) - 1];
$author_date = "Por {$autor_name} em {$art['datebr']}.";

// Formata o conteúdo:
$page_article = <<<HTML

<h2>{$art['art_title']}</h2>
<small>{$author_date}</small>
<div>{$art['art_content']}</div>

<a id="comments"></a>

HTML;

// Obtém a idade do usuário:
$age = get_age($art['user_birth']);

// Obtém mais artigos do autor:
$sql = <<<SQL

SELECT art_id, art_title FROM articles
WHERE art_author = '{$art['art_author']}'
	AND art_status = 'on'
    AND art_date <= NOW()
    AND art_id != '{$id}'
ORDER BY RAND()  
LIMIT 5; 

SQL;
$res = $conn->query($sql);

// Inicializa lista de artigos do autor:
$author_arts = '';

// Se o autor tem mais artigos...
if ($res->num_rows > 0) :

    // Prepara a listagem dos artigos:
    $author_arts = '<h4 class="more-articles">+ Artigos</h4><ul>';

    // Loop para obter cada artigo:
    while ($author_art = $res->fetch_assoc()) :

        // Concatena na listagem:
        $author_arts .= <<<HTML
    <li><a href="/view/?{$author_art['art_id']}">{$author_art['art_title']}</a></li>
HTML;

    endwhile;

    // Fecha a listagem de artigos:
    $author_arts .= '</ul>';

endif;

// Formata barra lateral:
$page_aside = <<<HTML

<div class="author">

    <img src="{$art['user_avatar']}" alt="{$art['user_name']}">
    <h4>{$art['user_name']}</h4>
    <h5 class="age">{$age} anos</h5>
    <p><small>{$art['user_bio']}</small></p>

</div>    
{$author_arts}

HTML;

// Atualiza as visualizações deste artigo:
$counter = intval($art['art_counter']) + 1;

// Atualiza o contador no database:
$sql = "UPDATE articles SET art_counter = '{$counter}' WHERE art_id = '{$id}';";
$conn->query($sql);

/***************
 * Comentários *
 ***************/

// Define variáveis:
$comments = $author_tools = '';
$counter = 0;

// Se usuário está logado...
if ($user) :

    // Se um comentário foi enviado...
    if ($_SERVER["REQUEST_METHOD"] == "POST") :

        // Obtém e sanitiza o comentário enviado:
        $new_comment = post_clean('comment', 'string');

        // Grava comentário no banco de dados:
        $sql = <<<SQL
    
    INSERT INTO comments (cmt_author, cmt_article, cmt_content) VALUES (
        '{$user['id']}',
        '{$id}',
        '{$new_comment}'
    );
    
    SQL;
        $conn->query($sql);

        // Comentário enviado com sucesso:
        $comments .= <<<HTML
        <div class="comment-sended">Comentário enviado com sucesso!</div>
        <script>
            // Vai para a âncora de comentários:
            location.href = "#comments";

            // Oculta feedback em alguns segundos:
            setTimeout(() => { 
                document.getElementsByClassName('comment-sended')[0].style.display = 'none';
            }, 5000);        
            
            // Previne o reenvio do formulário ao recarregar a página:
            if ( window.history.replaceState )
                window.history.replaceState( null, null, window.location.href );
        </script>
HTML;

    endif;

    // Action do form:
    $action = htmlspecialchars($_SERVER["PHP_SELF"]) . "?{$id}";

    // Formulário de comentários:
    $comments .= <<<HTML

<form action="{$action}" method="post" id="comment">
    <textarea name="comment" id="comment"></textarea>
    <button type="submit">Enviar</button>
</form>

<hr class="separator">

HTML;

// Se não está logado:
else :

    // Convite para logar-se:
    $comments .= <<<HTML

<p class="center"><a href="/login">Logue-se</a> para comentar.</p>
<hr class="separator">

HTML;

endif;

// Obtém todos os comentários para o artigo atual:
$sql = <<<SQL

SELECT
    comments.*,
    user_name, user_avatar,
    DATE_FORMAT(cmt_date, '%d/%m/%Y às %h:%i') AS date_br
FROM comments
INNER JOIN users ON cmt_author = user_id
WHERE cmt_article = '{$id}'
    AND cmt_status = 'on'
ORDER BY cmt_date DESC;

SQL;
$res = $conn->query($sql);

// Conta os comentários:
$total_comments = $res->num_rows;

// Se não tem comentários...
if ($total_comments < 1) :

    // Exibe mensagem convidando a comentar:
    $comments .= '<p class="center">Nenhum comentário encontrado. Seja a(o) primeira(o) a comentar!</p>';

// Se tem comentários...
else :

    // Loop para extrair cada comentário:
    while ($cmt = $res->fetch_assoc()) :

        // Formata comentário para HTML:
        $cmt_body = nl2br($cmt['cmt_content']);

        // Se o comentário é do usuário logado...
        if ($user) :
            if ($user['id'] == $cmt['cmt_author']) :

                // Mostra ferramentas do usuário:
                $author_tools = <<<HTML

<div class="author-tools">
    <a href="#edit" data-id="{$cmt['cmt_id']}"><i class="fa-solid fa-pen-to-square"></i></a>
    <a href="/delcom/?c={$cmt['cmt_id']}&a={$id}" onclick="return confirmDel()"><i class="fa-solid fa-trash"></i></a>
</div>

HTML;

            else :

                // Oculta ferramentas do usuário:
                $author_tools = '';

            endif;
        endif;

        // Bloco de comentário:
        $comments .= <<<HTML

<div class="comment-item">

    <div class="comment-info">
        <img src="{$cmt['user_avatar']}" alt="{$cmt['user_name']}">
        <div class="author-date">
            Por {$cmt['user_name']}<br>
            Em ${cmt['date_br']}.
        </div>
        {$author_tools}
    </div>
    <div class="comment-box" id="comment-{$cmt['cmt_id']}">
        <div class="comment-content" id="comment-body-{$cmt['cmt_id']}">{$cmt_body}</div>
    </div>

</div>

HTML;

    endwhile;

endif;

$page_article .= <<<HTML

<hr class="separator">
<h3>Comentários ($total_comments)</h3>
{$comments}

HTML;

/***********************************
 * Fim do código PHP desta página! *
 ***********************************/

/**
 * Inclui o cabeçalho do template nesta página:
 */
require($_SERVER['DOCUMENT_ROOT'] . '/_header.php');

/**
 * Exibe o conteúdo da página:
 */

echo <<<HTML

<article>{$page_article}</article>
<aside>{$page_aside}</aside>

<script>

    let allEdit = document.querySelectorAll('a[href="#edit"]');
    allEdit.forEach(item => {
        item.onclick = editComment;
    });

    function editComment(item) {
        commentId = this.getAttribute('data-id');
        commentBox = document.getElementById('comment-' + commentId);
        commentBody = document.getElementById('comment-body-' + commentId).innerHTML;
        commentBox.innerHTML = `
<form method="post" action="/edtcom/" class="edit-form" id="comment-form-` + commentId + `">
    <input type="hidden" name="c" value="` + commentId + `">
    <input type="hidden" name="a" value="{$id}">
    <textarea class="comment-content" name="b">`+ commentBody + `</textarea>
    <button type="submit">Salvar</button>
</form>
`;
        return false;
    }

    function confirmDel() {
        return confirm(`Tem certeza que deseja apagar este comentário?\n\nIsso é irrversível!`);
    }
</script>

HTML;

/**
 * Inclui o rodapé do template nesta página.
 */
require($_SERVER['DOCUMENT_ROOT'] . '/_footer.php');
