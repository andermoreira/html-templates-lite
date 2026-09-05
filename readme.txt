=== HTML Templates Lite ===
Contributors: andermoreira
Tags: template, html, css, no-theme, lightweight
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Crie templates HTML/CSS reusáveis, com listas de posts/categorias reais do WordPress, e aplique em posts, páginas, home, arquivos e busca, ignorando o tema ativo.

== Description ==

HTML Templates Lite deixa você criar um template HTML/CSS e aplicá-lo em qualquer post ou página, no lugar do tema ativo, só para aquelas URLs. O resto do site continua normal, com o tema de sempre.

= Por que este plugin existe =

A maioria dos construtores visuais (Elementor, Divi, Bricks, Oxygen) resolve "fugir do tema" trazendo de volta um editor visual inteiro, com sua própria biblioteca de widgets e dependências de JS. Este plugin faz só a parte de infraestrutura — desligar o tema para uma URL específica e servir HTML/CSS puro — e reaproveita o que o WordPress já traz pronto (`wp_head`, `wp_body_open`, `wp_footer`, o sistema de revisões, o editor de código nativo via `wp_enqueue_code_editor`) em vez de reinventar essas peças.

= O que o plugin faz =

* Um custom post type "Template HTML" onde você escreve o HTML e o CSS uma vez.
* Em qualquer post/página, um `<select>` escolhe qual template usar — o mesmo template pode ser reaproveitado em quantas páginas quiser.
* Um template pode incluir outro com `{{include:slug-do-template}}` (ex.: um "header" e um "footer" reusáveis, incluídos dentro de vários templates de página), com proteção contra loops de inclusão.
* `{{loop post_type="post" category="noticias" count="5"}}...{{/loop}}` traz uma lista de posts de verdade (com filtro por categoria/tag) pra dentro do template. O formulário **Inserir lista de posts** monta esse bloco na posição do cursor (ordenação inclui A–Z e data mais antiga; o campo Categoria some quando o tipo não usa essa taxonomia).
* Paginação nativa em templates de arquivo: com `paged="true"` no `{{loop}}` e a tag `{{pagination}}`, a navegação entre páginas funciona (na /page/2/ o loop lista os próximos posts em vez de repetir os primeiros).
* `{{assets_url}}` aponta pra pasta de assets do template (`uploads/htl-templates/{slug}/`, criada no salvamento) — envie os arquivos do template pronto (css/js/fonts/imagens) por FTP e referencie com caminhos relativos à tag. Um template incluído com `{{include}}` usa a própria pasta dele.
* `{{menu location="primary"}}` imprime o menu do WordPress (o `<ul>` com as classes nativas, sem wrapper) na marcação do template.
* Tags extras: `{{post_date}}`, `{{post_author}}`, `{{post_categories}}`, `{{post_tags}}`, `{{meta:chave}}` (campos personalizados/ACF — saída sempre escapada; `{{meta_url:chave}}` pra URLs), `{{comment_form}}` e `{{comments_list}}`.
* Regras de exibição em Ajustes: aplique um template a todos os posts de um tipo (ou de uma categoria) de uma vez — a escolha manual na metabox de cada post continua vencendo.
* O HTML do template processa shortcodes — plugins de formulário (Contact Form 7, Gravity Forms) entram colando o shortcode direto no template.
* Botão "Pré-visualizar template" na tela de edição — mostra como o template fica sem precisar salvar numa página de verdade primeiro.
* Botão "Salvar como cópia" — duplica um template existente pra partir dele em vez de começar do zero.
* Só usuários com a capability `unfiltered_html` (administradores, por padrão) veem a tela de criação/edição de templates — quem não tem essa permissão nem vê "Templates HTML" no menu.
* Editor de código com destaque de sintaxe (reaproveita o CodeMirror que já vem no core do WordPress). Na mesma tela, **Tags do template** insere placeholders no cursor (agrupadas por contexto: site, post, campo, arquivo). `{{include}}` e `{{menu}}` têm seletor — outros templates publicados e locais de menu do tema — em vez de você lembrar slug ou location.
* Tags dinâmicas simples: `{{post_title}}`, `{{post_content}}`, `{{post_excerpt}}`, `{{featured_image}}`, `{{permalink}}`, `{{site_title}}`, `{{site_tagline}}`, `{{current_year}}`.
* Posts protegidos por senha continuam protegidos — mostra o formulário de senha nativo do WP em vez do template.
* Histórico de revisão nativo do WordPress no HTML/CSS de cada template (a partir do WP 6.4).
* Tela em Ferramentas → Templates HTML Lite listando quem usa template e qual.
* Tela de Ajustes (em Templates HTML → Ajustes) pra escolher um template pra home, arquivo de categoria/tag/autor/data, busca e 404 — situações sem um post individual pra guardar essa escolha.
* Dentro de um template de arquivo, o `{{loop}}` filtra sozinho pela categoria/tag/autor/busca atual quando você não especifica isso manualmente — o mesmo template de categoria serve pra todas as categorias.
* Tags extras pra templates de arquivo: `{{archive_title}}`, `{{archive_description}}`, `{{search_query}}`.
* Dois filtros para desenvolvedores estenderem sem editar o plugin: `htl_supported_post_types` e `htl_template_tags`.

= O que o plugin NÃO faz (por escolha, não por limitação técnica) =

* Não tem editor visual de arrastar-e-soltar — é HTML e CSS escritos à mão.
* Não inclui biblioteca de elementos prontos.

= Nota de segurança sobre a saída não escapada =

O plugin imprime o HTML e o CSS do template sem `esc_html`/`wp_kses` no momento da exibição — a mesma decisão do bloco nativo "HTML personalizado" do WordPress e do painel "CSS Adicional" do Customizador. A restrição fica no momento da GRAVAÇÃO: só um usuário com a capability `unfiltered_html` (administradores, por padrão) pode salvar HTML totalmente livre; qualquer outro papel tem o conteúdo passado por `wp_kses_post` antes de ser salvo, a mesma sanitização que o WordPress já usa no conteúdo normal de um post. O CSS tem toda marcação HTML removida no salvamento (`wp_strip_all_tags`, a mesma abordagem do "CSS Adicional" do Customizador). Ver o comentário completo em `includes/class-htl-renderer.php`, logo acima da impressão do HTML.

= Pesquisa e referências =

Este plugin nasceu de uma pesquisa sobre alternativas ao Oxygen Builder e ao ecossistema de page builders do WordPress. Referências consultadas ao longo do processo:

* Oxygen Builder — desliga o sistema de temas do WordPress, referência direta da filosofia deste plugin: https://oxygenbuilder.com/
* Histórico Oxygen Classic → Oxygen 6 (rewrite sobre a base de código do Breakdance, depois que o AngularJS clássico foi descontinuado em 2021): https://www.webtng.com/getting-started-with-oxygen-6-walk-through-and-review/
* Zion Builder — builder GPL existente, com filosofia declarada de "markup mínimo": https://wordpress.org/plugins/zionbuilder/
* Bloco nativo "Custom HTML" do WordPress — precedente de HTML bruto já aceito pelo core: https://wordpress.com/support/wordpress-editor/blocks/custom-html-block/
* Block Bindings API (WP 6.5+) — alternativa nativa de dados dinâmicos considerada antes de optar pelo parser de tags `{{...}}`, mais simples para este escopo: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/
* `wp_enqueue_code_editor` — editor de código nativo do WordPress, mesmo motor usado no Editor de Temas: https://developer.wordpress.org/reference/functions/wp_enqueue_code_editor/
* Lax Block Binder — plugin recente (ago/2026) de binding visual de post meta via Block Bindings API, mapeado durante a pesquisa como funcionalidade adjacente, não sobreposta: https://wordpress.org/plugins/lax-block-binder/
* `wp_post_revision_meta_keys` / framework de revisões de post meta (WP 6.4) — base da funcionalidade de histórico de versões: https://make.wordpress.org/core/2023/10/24/framework-for-storing-revisions-of-post-meta-in-6-4/

== Installation ==

1. Envie a pasta `html-templates-lite` inteira para `/wp-content/plugins/`, ou instale o .zip por Plugins → Adicionar novo → Enviar plugin.
2. Ative o plugin no menu Plugins.
3. Vá em Templates HTML → Adicionar novo, dê um nome (o slug vira o identificador usado em `{{include:slug}}`) e escreva o HTML e o CSS. Na mesma tela, **Tags do template** insere placeholders no cursor; **Inserir lista de posts** monta um `{{loop}}`.
4. Edite qualquer post ou página, escolha esse template no seletor "Template HTML/CSS", e publique.

== Frequently Asked Questions ==

= Isso funciona com qualquer tema? =
Sim. Para páginas com um template escolhido, o tema é completamente ignorado. Para as demais páginas do site, nada muda — o tema continua no controle normalmente.

= Posso usar o mesmo template em várias páginas? =
Sim — esse é o ponto principal da v0.2.0. O HTML/CSS mora no template (custom post type "Template HTML"), e cada post/página só guarda uma referência a ele. Editar o template atualiza todas as páginas que o usam.

= E se eu excluir ou despublicar um template que está em uso? =
As páginas que o usavam voltam automaticamente a usar o tema normal, em vez de quebrar ou mostrar uma página vazia.

= Dá pra ver como o template fica sem publicar numa página? =
Sim — na tela de edição do template, o botão "Pré-visualizar template" abre uma aba nova mostrando o resultado. Ele usa a última versão SALVA; mudanças ainda não salvas no editor não aparecem até você salvar.

= Dá pra duplicar um template existente? =
Sim, o botão "Salvar como cópia" cria um novo template (como rascunho) com o mesmo HTML/CSS do original.

= Qualquer usuário pode criar um template? =
Não — só quem tem a capability `unfiltered_html` (administradores, por padrão, em instalação single-site) vê e acessa "Templates HTML" no menu. Isso evita confusão: sem essa permissão, HTML potencialmente perigoso seria filtrado ao salvar, e a pessoa acharia que o plugin "comeu" o que ela escreveu.

= Como eu trago uma lista de posts (tipo um blog) pra dentro do template? =
Na tela de edição do template, abra **Inserir lista de posts**: escolha o tipo de conteúdo, a categoria, quantidade e ordenação, e clique em **Inserir lista no HTML** — o bloco `{{loop}}...{{/loop}}` entra na posição do cursor. Dentro do bloco, as mesmas tags de sempre (`{{post_title}}`, `{{post_excerpt}}`, `{{permalink}}`) passam a se referir a cada post da lista. Você também pode clicar as tags em **Tags do template** em vez de digitá-las.

= Como inserir uma tag sem digitar a sintaxe? =
Abra **Tags do template** e clique no chip. A tag entra no HTML, na posição do cursor. `{{include}}` lista os outros templates publicados; `{{menu}}` lista os locais de menu do tema. Em `{{meta:chave}}`, o editor seleciona `chave` para você só digitar o nome do campo. Isso não é um construtor visual: o destino continua sendo HTML.

= Preciso saber PHP? =
Não. HTML e CSS são suficientes para o uso básico. Dados dinâmicos usam tags como `{{post_title}}` (clicáveis em **Tags do template**), e reuso de trechos usa `{{include:slug}}`, sem PHP.

= É seguro colar HTML de qualquer lugar? =
Só usuários com a capability `unfiltered_html` (administradores, por padrão, em instalação single-site) podem salvar HTML sem filtro algum. Qualquer outro papel tem o conteúdo passado pelo `wp_kses_post` ao salvar — a mesma sanitização que o WordPress já usa no conteúdo normal de um post.

= Posts protegidos por senha funcionam? =
Sim — tanto em posts individuais (mostra o formulário de senha padrão do WordPress em vez do template) quanto dentro de `{{loop}}`: o conteúdo de um post com senha nunca é exposto, nem na lista.

= As mudanças no template têm histórico de versões? =
Sim, a partir do WordPress 6.4: o HTML e o CSS de cada template são revisionados como qualquer conteúdo de post, então dá pra ver e restaurar versões anteriores pela tela padrão de revisões do WordPress.

= Funciona na home page ou em páginas de arquivo/categoria/busca? =
Sim, a partir da 0.5.0. Vá em Templates HTML → Ajustes e escolha um template pra cada situação (home, categoria, tag, autor, data, busca, 404). Se a home do site for uma Página estática, configure o template dela direto naquela Página, do jeito de sempre — o ajuste de "Página inicial" só vale quando a home mostra os posts recentes.

= Meu template de categoria precisa saber o slug da categoria? =
Não — dentro de um `{{loop}}`, se você não informar o atributo `category`, ele é preenchido sozinho com a categoria da URL atual (o mesmo vale pra `tag`, `author` e `s`/busca). Um valor que você escrever explicitamente sempre tem prioridade sobre esse preenchimento automático.

= Como paginar um template de categoria/arquivo? =
No formulário **Inserir lista de posts**, marque **Paginar nesta listagem** — ou adicione `paged="true"` no bloco `{{loop}}` — e coloque `{{pagination}}` onde a navegação deve aparecer. Na /page/2/, /3/... o loop passa a listar os posts seguintes em vez de repetir os primeiros. O número de páginas segue Ajustes → Leitura ("No máximo X posts por página"). Marque paginação só em home, categoria, tag, autor ou busca.

= Como uso os arquivos (css/js/fonts/imagens) de um template pronto? =
Salve o template uma vez: o plugin cria a pasta `uploads/htl-templates/{slug}/` e mostra o caminho na tela de edição. Envie os arquivos por FTP ou pelo gerenciador de arquivos do host e referencie com `{{assets_url}}` — ex.: `<link rel="stylesheet" href="{{assets_url}}/css/main.css">`. Atenção: se o slug do template mudar, a pasta muda junto (os arquivos precisam ser movidos).

= Como aplicar um template a todos os posts de uma categoria? =
Em Templates HTML → Ajustes, seção "Posts e páginas por regra": escolha o tipo de conteúdo, a categoria (opcional) e o template. A regra vale pra qualquer post do tipo que não tenha template escolhido na metabox — e a metabox vence quando tem. A ordem das regras importa: a primeira que casar ganha.

== Limitações conhecidas ==

* `{{loop}}` aninhado (um loop dentro de outro) não é suportado — o bloco interno é tratado como texto e o resultado sai quebrado. Use um loop por template, ou componha trechos com `{{include}}`.
* A tag `{{pagination}}` reflete a consulta principal do WordPress: o número de páginas segue Ajustes → Leitura ("No máximo X posts por página"). Alinhe o `count` do loop com esse valor pra lista e a navegação coincidirem.
* `paged="true"` só faz sentido quando o loop lista o mesmo conteúdo do contexto atual (template de arquivo). Num loop com filtros próprios (ex.: `category="destaques"` numa página de categoria), a paginação da URL não corresponde ao que ele exibe.
* Cada `{{loop}}` tem teto de 50 posts por página (proteção de servidor). Alguns atributos de consulta avançados (taxonomias customizadas, ano/mês) ficam disponíveis só via o filtro `htl_loop_query_args`.
* `{{meta:chave}}` não expõe campos protegidos (`is_protected_meta`, incluindo todo prefixo `_`) nem valores complexos (arrays), e a saída é sempre escapada (`esc_html`; `esc_url` na variante `{{meta_url:chave}}`). Meta de post protegido por senha nunca sai — nem dentro de `{{loop}}`. A resolução roda só no HTML autoriado do template: um post cujo conteúdo contenha o literal `{{meta:x}}` não injeta resolução.
* Assets do template são enviados por FTP/gerenciador de arquivos — o plugin não faz upload, pra não criar superfície de ataque.

== Changelog ==

= 0.6.3 =
* Tags do template na tela de edição agora são clicáveis: inserem no HTML, na posição do cursor, agrupadas por contexto (site, post, campo, arquivo).
* Incluídas na referência as tags que o renderer já tinha: `{{site_title}}`, `{{site_tagline}}`, `{{current_year}}`.
* Pickers para `{{include:slug}}` (outros templates publicados) e `{{menu location="..."}}` (locais registrados pelo tema), sem novos accordions de widgets.
* Helper de loop: ordenação A–Z/Z–A e data mais antiga; o bloco gerado passa a emitir `order`. Layout em duas colunas com rótulos acima dos campos.

= 0.6.2 =
* Segurança (auditoria): `{{meta:chave}}` agora sai sempre escapada (`esc_html`) — post meta não passa por kses no save do core, e o valor de um campo escrito por autor sem `unfiltered_html` podia virar XSS armazenado na página do template.
* Segurança (auditoria): a resolução de meta roda só sobre o HTML autoriado do template, fora dos corpos de `{{loop}}` — antes, um post cujo conteúdo contivesse o literal `{{meta:x}}` disparava a resolução na passada externa (XSS de segunda ordem, sem cooperação do admin).
* Nova tag `{{meta_url:chave}}` — `esc_url` pra uso em `href`/`src`, neutralizando esquemas como `javascript:`.
* Segurança (auditoria): campos protegidos via `is_protected_meta` (antes: só prefixo `_`); meta de post protegido por senha não é exposta, nem dentro de `{{loop}}`.
* Segurança (auditoria): o contexto de preview (`htl_preview_context`) exige `read_post` — papéis com `unfiltered_html` sem `read_private_posts` não renderizam mais rascunhos/privados de terceiros.
* Segurança (auditoria): "Salvar como cópia" virou POST com nonce — como GET mutante, prefetchers podiam disparar duplicações.

= 0.6.1 =
* Interface traduzida para inglês (convenção do diretório de plugins do WordPress/GlotPress) — tradução pt-BR incluída em `languages/`.
* Novo: `composer.json` com WordPress Coding Standards, workflow de CI no GitHub Actions (php -l 7.4–8.4, node, PHPCS) e `.distignore` pra gerar o .zip de distribuição.
* Limpeza: capability inexistente `create_htl_templates` removida da lista de caps mapeadas (a criação já cai em `edit_htl_templates`).

= 0.6.0 =
* Novo: tag `{{assets_url}}` apontando pra pasta de assets do template (`uploads/htl-templates/{slug}/`, criada no salvamento e exibida na tela de edição). Cada `{{include}}` resolve a própria pasta — header/footer reusáveis são autossuficientes.
* Novo: tag `{{menu location="primary"}}` imprime o menu nativo do WordPress (`<ul>` com classes WP, sem wrapper).
* Novas tags: `{{post_date}}`, `{{post_author}}`, `{{post_categories}}`, `{{post_tags}}`, `{{meta:chave}}` (campos personalizados/ACF; campos protegidos não são expostos), `{{comment_form}}` e `{{comments_list}}`.
* Novo: regras de exibição em Ajustes ("Posts e páginas por regra") — template aplicado a todos os posts de um tipo (ou de uma categoria), sem editar página por página. A metabox de cada post vence a regra.
* O HTML do template agora processa shortcodes — escape hatch pra plugins de formulário e componentes.
* Novo filtro `htl_assets_url` pra desenvolvedores alterarem a pasta de assets.

= 0.5.2 =
* Novo: paginação em templates de arquivo — atributo `paged="true"` no `{{loop}}` e tag `{{pagination}}` (navegação entre páginas). O formulário "Inserir lista de posts" ganhou a caixinha "Paginação (templates de arquivo)", que insere os dois pra você. Na /page/2/ o loop lista os próximos posts em vez de repetir os primeiros.
* O `{{loop}}` agora recusa `post_type="htl_template"` — evita expor títulos de templates em páginas públicas.
* Em Ferramentas → Templates HTML Lite, os links de edição só aparecem pra quem tem permissão de editar (antes podiam aparecer vazios).
* Nova seção "Limitações conhecidas" na documentação.

= 0.5.1 =
* Corrigido: o histórico de revisões do HTML/CSS não funcionava de fato — o CPT de template não tinha o suporte `revisions`, exigido pelo framework de revisões de post meta do WP 6.4. Agora as revisões são criadas e restauráveis.
* Corrigido: `{{post_content}}` dentro de um `{{loop}}` expunha o conteúdo completo de posts protegidos por senha. Agora mostra o formulário de senha nativo, com o mesmo comportamento de `the_content()` no loop do core.
* Corrigido: a sanitização de CSS não cobria variações de caixa de `</style>` (ex.: `</STYLE>`), que permitiam fechar a tag e injetar HTML. Agora usa `wp_strip_all_tags` — mesma abordagem do "CSS Adicional" do Customizador — que remove qualquer marcação, em qualquer caixa.
* A URL de pré-visualização (`?htl_preview=ID`) agora exige usuário logado e envia cabeçalhos no-cache, para não parar em cache de página.
* A desinstalação agora remove também templates em auto-draft ou na lixeira (o valor `any` deixava esses status de fora).
* As meta keys do plugin passam a ser registradas só nos post types onde fazem sentido: HTML/CSS no CPT de template, seletor nos post types suportados.

= 0.5.0 =
* Nova tela de Ajustes (Templates HTML → Ajustes) para escolher um template de home, categoria, tag, autor, data, busca e 404.
* `{{loop}}` agora auto-detecta categoria/tag/autor/busca da URL atual quando o atributo correspondente não é especificado — um template de categoria funciona pra qualquer categoria.
* Novas tags `{{archive_title}}`, `{{archive_description}}` e `{{search_query}}`, disponíveis em templates de arquivo/busca (fora do contexto de um post específico).
* Novo filtro `htl_archive_conditions` (no renderer) e `htl_archive_condition_labels` (na tela de Ajustes) para desenvolvedores adicionarem condições extras, como um arquivo de custom post type específico.

= 0.4.0 =
* Nova capability restrita: só usuários com `unfiltered_html` veem e acessam a tela de Templates HTML (criação/edição/exclusão).
* Novo botão "Pré-visualizar template", com URL própria (`?htl_preview=ID`) que não exige uma página real associada.
* Novo botão "Salvar como cópia" para duplicar um template existente.
* O campo "Categoria" do helper de loop agora se esconde automaticamente quando o tipo de conteúdo escolhido não usa essa taxonomia.

= 0.3.0 =
* Nova tag `{{loop post_type="..." category="..." count="..." orderby="..."}}...{{/loop}}` para trazer listas de posts/categorias reais do WordPress para dentro de um template.
* Novo formulário visual na tela de edição do template ("Inserir lista de posts") que monta o bloco `{{loop}}` sem precisar escrever a sintaxe na mão.
* Novo filtro `htl_loop_query_args` para desenvolvedores ajustarem a consulta do loop (ex.: taxonomias customizadas).
* Corrigido vazamento do post global (`$post`) entre iterações de um loop e o restante do template, que podia afetar plugins que leem `$post` diretamente dentro do filtro `the_content`.

= 0.2.0 =
* HTML/CSS agora moram num custom post type "Template HTML" reusável, em vez de duplicados em cada post (reusabilidade).
* Nova tag `{{include:slug}}` para incluir um template dentro de outro, com proteção contra loop de inclusão.
* Corrigida a ausência da tag `<title>` nas páginas renderizadas pelo plugin.
* Adicionado `wp_body_open()` logo após a abertura do `<body>`.
* Posts protegidos por senha agora mostram o formulário de senha nativo do WP, em vez de expor o template.
* HTML/CSS de cada template agora têm histórico de revisão nativo do WordPress (requer WP 6.4+).
* `Requires at least` subiu de 6.0 para 6.4 por causa do item acima.

= 0.1.0 =
* Versão inicial: metabox de template por post, renderer via `template_include`, parser de tags dinâmicas, tela de listagem em Ferramentas.
