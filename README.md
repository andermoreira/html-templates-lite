# HTML Templates Lite

> Crie templates HTML/CSS reusáveis e aplique em qualquer post, página ou custom post type, ignorando o tema ativo só nessas URLs — sem page builder, sem dependências.

![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759B?style=flat-square&logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Versão](https://img.shields.io/badge/vers%C3%A3o-0.6.3-blue?style=flat-square)
![Licença](https://img.shields.io/badge/licen%C3%A7a-GPL--2.0%2B-green?style=flat-square)

## O que é

A maioria dos construtores visuais (Elementor, Divi, Bricks, Oxygen) resolve "fugir do tema" trazendo de volta um editor visual inteiro. Este plugin faz só a parte de infraestrutura — desligar o tema para uma URL específica e servir HTML/CSS puro — reaproveitando o que o WordPress já traz pronto: `wp_head`, `wp_body_open`, `wp_footer`, o sistema de revisões e o editor de código nativo (CodeMirror). É para quem sabe escrever HTML e CSS e quer controle total do markup, sem carregar uma biblioteca de widgets.

O resto do site continua normal, com o tema de sempre. Se um template for excluído ou despublicado, as páginas que o usavam voltam automaticamente ao tema.

## Recursos

- **Template reusável** — o HTML/CSS mora num custom post type "Template HTML"; cada post/página só guarda uma referência. Editar o template atualiza todas as páginas que o usam.
- **`{{include:slug}}`** — inclui um template dentro de outro (ex.: header e footer reusáveis), com proteção contra loops de inclusão.
- **`{{loop}}`** — lista posts reais do WordPress dentro do template, com filtro por categoria/tag/autor/busca. Em templates de arquivo, o loop auto-detecta o contexto da URL: o mesmo template de categoria serve para todas as categorias.
- **Paginação nativa** — com `paged="true"` no `{{loop}}` e a tag `{{pagination}}`, a navegação entre páginas funciona (na `/page/2/` o loop lista os próximos posts em vez de repetir os primeiros).
- **Assets por template** — `{{assets_url}}` aponta pra pasta do template em `uploads/htl-templates/{slug}/` (criada no salvamento): envie os arquivos do template pronto por FTP e referencie com caminhos relativos.
- **Menus nativos** — `{{menu location="primary"}}` imprime o menu do WordPress na marcação do template.
- **Regras de exibição** — aplique um template a todos os posts de um tipo (ou de uma categoria) na tela de Ajustes; a metabox de cada post vence quando tem escolha manual.
- **Shortcodes processados** — plugins de formulário (Contact Form 7, Gravity Forms) entram colando o shortcode no template.
- **Home e arquivos** — tela de Ajustes para escolher template de home, categoria, tag, autor, data, busca e 404.
- **Pré-visualizar** e **Salvar como cópia** na tela de edição.
- **Editor de código** com destaque de sintaxe (CodeMirror nativo do core). Tags clicáveis inserem no HTML, na posição do cursor; `{{include}}` e `{{menu}}` têm seletor; o formulário **Inserir lista de posts** monta o bloco `{{loop}}`.
- **Revisões nativas** — histórico de versões do HTML/CSS pela tela padrão de revisões (WP 6.4+).
- **Posts com senha continuam protegidos** — inclusive dentro de `{{loop}}`.
- **Extensível sem editar o plugin** — cinco filtros para desenvolvedores.

## Instalação

1. Envie a pasta `html-templates-lite` para `/wp-content/plugins/`, ou instale o .zip em Plugins → Adicionar novo → Enviar plugin.
2. Ative o plugin.
3. Vá em **Templates HTML → Adicionar novo**, dê um nome (o slug vira o identificador usado em `{{include:slug}}`) e escreva o HTML e o CSS. Na mesma tela, **Tags do template** insere placeholders no cursor; **Inserir lista de posts** monta um `{{loop}}`.
4. Edite qualquer post ou página, escolha o template no seletor "Template HTML/CSS" e publique.

## Uso

### Editor do template

Abaixo dos campos HTML/CSS, dois painéis recolhíveis:

- **Inserir lista de posts** — escolhe tipo, categoria (some se o tipo não tiver essa taxonomia), quantidade, ordenação (data mais recente/antiga, título A–Z/Z–A, aleatório) e paginação. O botão insere o bloco `{{loop}}` na posição do cursor; com paginação marcada, insere também `{{pagination}}`.
- **Tags do template** — clique numa tag para inseri-la no cursor. As tags estão agrupadas por contexto (site, post, campo, arquivo). `{{include}}` lista os outros templates publicados; `{{menu}}` lista os locais de menu do tema. Em `{{meta:chave}}`, o editor seleciona `chave` para você só digitar o nome do campo.

Isso não é um page builder: o destino continua sendo HTML. Os painéis só poupam memorizar a sintaxe.

### Tags dinâmicas

| Tag | Retorna | Contexto |
|---|---|---|
| `{{post_title}}` | Título | Post (e cada iteração de `{{loop}}`) |
| `{{post_content}}` | Conteúdo processado | Post |
| `{{post_excerpt}}` | Resumo | Post |
| `{{post_date}}` | Data (formato de Ajustes → Geral) | Post |
| `{{post_author}}` | Nome de exibição do autor | Post |
| `{{post_categories}}` | Links das categorias, separados por vírgula | Post |
| `{{post_tags}}` | Links das tags, separados por vírgula | Post |
| `{{featured_image}}` | URL da imagem destacada (tamanho `full`) | Post |
| `{{permalink}}` | Link permanente | Post |
| `{{meta:chave}}` | Campo personalizado, escapado (`esc_html`; sem campos protegidos) | Post |
| `{{meta_url:chave}}` | Campo como URL segura (`esc_url`, neutraliza `javascript:`) | Post |
| `{{comment_form}}` | Formulário de comentários nativo | Post |
| `{{comments_list}}` | Comentários aprovados (marcação nativa) | Post |
| `{{assets_url}}` | URL da pasta de assets do template | Sempre |
| `{{menu location="primary"}}` | Menu nativo (`<ul>` sem wrapper) | Sempre |
| `{{site_title}}` | Nome do site | Sempre |
| `{{site_tagline}}` | Descrição do site | Sempre |
| `{{current_year}}` | Ano atual localizado | Sempre |
| `{{archive_title}}` | Título do arquivo | Home/arquivo/busca/404 |
| `{{archive_description}}` | Descrição do arquivo | Home/arquivo/busca/404 |
| `{{search_query}}` | Termo buscado | Busca |
| `{{pagination}}` | Navegação entre páginas | Home/arquivo/busca/404 |

### Include

```html
{{include:meu-header}}
<main>...</main>
{{include:meu-footer}}
```

### Loop

```html
{{loop post_type="post" category="noticias" count="5" orderby="date" order="DESC"}}
  <article>
    <h2><a href="{{permalink}}">{{post_title}}</a></h2>
    <p>{{post_excerpt}}</p>
  </article>
{{/loop}}
```

Atributos aceitos: `post_type`, `category`, `tag`, `author`, `s` (busca), `count` (máx. 50), `orderby`, `order`. Em template de categoria/tag/autor/busca, o atributo correspondente é preenchido automaticamente com o contexto atual da URL quando omitido.

### Paginação

Em templates de arquivo, adicione `paged="true"` ao `{{loop}}` e coloque `{{pagination}}` onde a navegação deve aparecer:

```html
{{loop post_type="post" count="10" paged="true"}}
  <article>...</article>
{{/loop}}
{{pagination}}
```

O `{{pagination}}` usa a consulta principal do WordPress — o número de páginas segue Ajustes → Leitura ("No máximo X posts por página"). Em contextos com uma página só, a tag simplesmente não imprime nada. A navegação sai com a classe `htl-pagination` para você estilizar no CSS do template.

### Assets do template

Salve o template uma vez: o plugin cria `uploads/htl-templates/{slug}/` e mostra o caminho na tela de edição. Envie os arquivos do template pronto (css/js/fonts/imagens) por FTP ou gerenciador de arquivos do host e referencie com a tag:

```html
<link rel="stylesheet" href="{{assets_url}}/css/main.css">
<script src="{{assets_url}}/js/main.js"></script>
```

Um template incluído com `{{include}}` resolve a própria pasta dele — um header/footer reusável é autossuficiente.

### Menus

```html
<nav>
  {{menu location="primary"}}
</nav>
```

Imprime o `<ul>` com as classes nativas do WordPress (`menu`, `menu-item`, `current-menu-item`...), sem wrapper — a `<nav>` e o CSS ficam com o template.

### Regras de exibição

Em **Templates HTML → Ajustes**, seção "Posts e páginas por regra": escolha tipo de conteúdo + categoria (opcional) + template. A regra vale para qualquer post do tipo sem escolha manual; a metabox de cada post vence a regra; a primeira regra que casar ganha.

### Shortcodes

Shortcodes colados no HTML do template são processados como num post normal — é assim que formulários do Contact Form 7, Gravity Forms e similares entram no template. Shortcode não registrado permanece como texto.

## Filtros para desenvolvedores

```php
// Adicionar um post type próprio ao seletor de templates.
add_filter( 'htl_supported_post_types', function ( $types ) {
    $types[] = 'produto';
    return $types;
} );

// Adicionar tags dinâmicas próprias.
add_filter( 'htl_template_tags', function ( $tags, $post_id ) {
    if ( $post_id ) {
        $tags['{{preco}}'] = get_post_meta( $post_id, 'preco', true );
    }
    return $tags;
}, 10, 2 );

// Ajustar a consulta de um {{loop}} (ex.: filtro por ano/mês em arquivo de data).
add_filter( 'htl_loop_query_args', function ( $args, $atts ) {
    if ( is_date() ) {
        $args['year']     = get_query_var( 'year' );
        $args['monthnum'] = get_query_var( 'monthnum' );
    }
    return $args;
}, 10, 2 );

// Adicionar condições de arquivo à tela de Ajustes (renderer + labels).
add_filter( 'htl_archive_conditions', function ( $conditions ) {
    $conditions['meu_cpt'] = 'is_post_type_archive';
    return $conditions;
} );
```

## Segurança

O plugin imprime o HTML e o CSS do template sem `esc_html`/`wp_kses` na exibição — é a funcionalidade central, no mesmo espírito do bloco nativo "HTML personalizado" e do "CSS Adicional" do Customizador. A garantia fica no salvamento:

- Só usuários com a capability `unfiltered_html` (administradores, por padrão, em single-site) podem criar/editar templates — a tela nem aparece para os demais.
- Sem essa permissão, o HTML é filtrado por `wp_kses_post` (a mesma sanitização do conteúdo normal de posts).
- O CSS tem toda marcação HTML removida no salvamento (`wp_strip_all_tags`), impossibilitando fechar a tag `<style>` e injetar HTML.

## Limitações conhecidas

- `{{loop}}` aninhado (um loop dentro de outro) não é suportado — o bloco interno é tratado como texto e o resultado sai quebrado. Use um loop por template, ou componha trechos com `{{include}}`.
- A tag `{{pagination}}` reflete a consulta principal do WordPress; alinhe o `count` do loop com Ajustes → Leitura pra lista e a navegação coincidirem.
- `paged="true"` só faz sentido quando o loop lista o mesmo conteúdo do contexto atual (template de arquivo); num loop com filtros próprios, a paginação da URL não corresponde ao que ele exibe.
- Cada `{{loop}}` tem teto de 50 posts por página (proteção de servidor). Atributos de consulta avançados (taxonomias customizadas, ano/mês) ficam disponíveis via o filtro `htl_loop_query_args`.
- `{{meta:chave}}` não expõe campos protegidos (`is_protected_meta`, incluindo todo prefixo `_`) nem valores complexos (arrays), e a saída é sempre escapada (`esc_html`; `esc_url` na variante `{{meta_url:chave}}`). Meta de post com senha nunca sai — nem dentro de `{{loop}}`. A resolução roda só no HTML autoriado do template: post content contendo o literal `{{meta:x}}` não injeta resolução.
- Assets são enviados por FTP/gerenciador de arquivos — o plugin não faz upload, para não criar superfície de ataque. Se o slug do template mudar, a pasta muda junto (mova os arquivos).

## Links

| Recurso | Link |
|---|---|
| Repositório | [GitHub](https://github.com/andermoreira/html-templates-lite) |
| Changelog completo | [readme.txt](./readme.txt) |

## Licença

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
