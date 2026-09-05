# Kit de teste manual — HTML Templates Lite 0.6.0

Um conjunto de 4 templates + assets reais para validar o plugin de ponta a ponta, cobrindo todos os recursos da versão 0.6.0: `{{include}}`, `{{assets_url}}` por template, `{{menu}}`, tags de post, `{{meta:chave}}`, `{{loop}}` com paginação, `{{pagination}}`, arquivos, busca, regras de exibição, comentários, revisões, preview e duplicação.

> **Atenção:** esta pasta é ferramenta de desenvolvimento. Não a inclua no .zip de distribuição do plugin — ela não faz parte do código.

```
test-kit/
├── README.md                      ← este arquivo
├── templates/                     ← conteúdo a COLAR no editor HTML de cada template
│   ├── 1-kit-header.html
│   ├── 2-kit-footer.html
│   ├── 3-kit-post.html
│   └── 4-kit-archive.html
└── uploads/htl-templates/         ← conteúdo a SUBIR pra wp-content/uploads/htl-templates/
    ├── kit-header/css/style.css   ← CSS global (header escuro = assets OK)
    ├── kit-header/js/test.js      ← log no console + classe kit-js-ok no <html>
    ├── kit-post/css/page.css      ← CSS próprio do singular (título com barra laranja)
    └── kit-archive/css/page.css   ← CSS próprio do arquivo (cabeçalho azul)
```

## 1. Preparação (no WordPress)

1. **Ative um tema clássico qualquer** (ex.: Twenty Twenty-One) — o plugin deve ignorá-lo só nas páginas com template.
2. **Ajustes → Leitura**: "No máximo `2` posts por página" (necessário pra paginação — `{{pagination}}` usa a consulta principal).
3. **Aparência → Menus**: crie um menu com 3–4 páginas/posts e atribua à location "Primary". Se o tema usar outro nome, descubra o slug da location (ex.: no código do tema, `wp_nav_menu( 'theme_location' => ... )`) e ajuste o `{{menu location="..."}}` no template 1.
4. **Crie 5 posts** de teste, com:
   - categorias variadas; pelo menos 1 na categoria **"Destaques"**;
   - imagem destacada em 1 deles (os outros ficam com `src` vazio de propósito);
   - tags em 1 deles;
   - 1 post **protegido por senha** (Publicar → Visibilidade → Protegido por senha);
   - no post do item 5 abaixo, **campo personalizado**: `fonte` → valor `Kit de teste 0.6.0` (Editor clássico → Opções de tela → Campos personalizados).
5. **Um post com comentários**: deixe comentários abertos; depois de validar o formulário, aprove um comentário (Comentários) pra testar `{{comments_list}}`.

## 2. Criar os 4 templates

Em **Templates HTML → Adicionar novo**, crie na ordem — o título precisa gerar EXATAMENTE o slug indicado (confira o permalink abaixo do título; se o WP adicionar `-2`, ajuste o `{{include}}`):

| # | Título | Slug esperado | Colar de | Onde é usado |
|---|---|---|---|---|
| 1 | `Kit Header` | `kit-header` | `templates/1-kit-header.html` | incluído nos demais |
| 2 | `Kit Footer` | `kit-footer` | `templates/2-kit-footer.html` | incluído nos demais |
| 3 | `Kit Post` | `kit-post` | `templates/3-kit-post.html` | posts individuais |
| 4 | `Kit Archive` | `kit-archive` | `templates/4-kit-archive.html` | home/categoria/busca (Ajustes) |

✅ **[ ] Pasta de assets criada no save** — ao salvar, o plugin cria `wp-content/uploads/htl-templates/{slug}/` (confira na tela de edição: o caminho aparece logo abaixo dos botões de preview/cópia).

## 3. Subir os assets

Copie o conteúdo de `test-kit/uploads/htl-templates/` pra dentro de `wp-content/uploads/htl-templates/` (FTP, cPanel, gerenciador de arquivos do host, ou plugin de acesso a arquivos). Estrutura final:

```
wp-content/uploads/htl-templates/
├── kit-header/css/style.css   + kit-header/js/test.js
├── kit-post/css/page.css
└── kit-archive/css/page.css
```

## 4. Aplicar

1. **Post individual**: no post com campo personalizado `fonte`, escolha **Kit Post** na metabox "Template HTML/CSS" e publique.
2. **Regra por categoria**: Templates HTML → **Ajustes** → seção "Posts e páginas por regra" → Adicionar regra: Tipo = `Posts`, Categoria = `Destaques`, Template = `Kit Post`. Salvar.
3. **Arquivos**: na mesma tela de Ajustes, defina **Página inicial** = Kit Archive, **Arquivo de categoria** = Kit Archive, **Resultados de busca** = Kit Archive. Salvar.

## 5. Checklist de validação

### Assets e composição (em qualquer página com template)

- [ ] **Header escuro** (`css/style.css` do kit-header carregado) e footer com a borda azul — os dois `{{include}}` resolveram.
- [ ] Console mostra `[HTML Templates Lite] JS do kit-header carregado` e o `<html>` tem classe `kit-js-ok` — assets **.js** da pasta do include funcionam.
- [ ] **Singular**: título com barra laranja à esquerda (`kit-post/css/page.css` — pasta própria do template, não a do include).
- [ ] **Arquivo**: cabeçalho azul (`kit-archive/css/page.css` — outra pasta própria, provando que raiz e includes resolvem pastas diferentes).

### Tags

- [ ] Singular: título, autor, data (formato de Ajustes → Geral), categorias e tags aparecem; o conteúdo renderiza com `wpautop`/shortcodes como num post normal.
- [ ] Singular: a caixa laranja mostra `Kit de teste 0.6.0` — **`{{meta:fonte}}`** funcionou.
- [ ] Singular: imagem destacada visível no post com thumbnail; nos outros, `src=""` vazio (comportamento documentado).
- [ ] Arquivo: `{{archive_title}}` mostra "Destaques" / "Categoria: …" conforme a URL; na busca, `{{search_query}}` volta preenchido.
- [ ] Footer mostra o ano atual — `{{current_year}}` e `{{site_title}}`/`{{site_tagline}}` OK.

### Menu

- [ ] `<ul class="menu">` com os itens do menu dentro do `<nav>` escuro; a página atual ganha `current-menu-item` (item destacado em branco).
- [ ] (Fonte → inspecionar) Sem wrapper extra além do `<nav>` do template.

### Loop + paginação

- [ ] Arquivo lista **2 cards** por página (count do loop).
- [ ] `{{pagination}}` imprime 3 páginas; navegar pra `/page/2/` e `/page/3/` traz posts **diferentes** (não repetidos).
- [ ] Na página 1, o link "2" aponta pra `/page/2/`; a página corrente tem classe `current`.

### Regras de exibição

- [ ] Post da categoria "Destaques" **sem** escolha manual abre com o Kit Post (regra casou).
- [ ] Post sem a categoria (sem escolha manual) usa o **tema** (nenhuma regra casa).
- [ ] No post configurado no passo 4.1 (escolha **manual**), troque a metabox pra outro template — a metabox vence a regra.

### Arquivos, busca e comentários

- [ ] Home do site (`/`) usa o Kit Archive (condição "home" do Ajustes).
- [ ] `/category/destaques/` também usa (condição "category").
- [ ] Buscar pelo formulário do próprio template: resultados com Kit Archive + termo em `{{search_query}}` + loop filtrado pela busca.
- [ ] Post com comentários: formulário nativo aparece; após aprovar um comentário, ele aparece via `{{comments_list}}` com a marcação nativa.

### Telas administrativas

- [ ] **Pré-visualizar template** abre `/?htl_preview=ID` numa aba nova mostrando o template (com header/footer).
- [ ] **Salvar como cópia** cria "…(cópia)" como rascunho com o mesmo HTML/CSS.
- [ ] Edite o Kit Header duas vezes e veja o histórico em **Revisões** — o HTML/CSS é versionado.
- [ ] **Ferramentas → Templates HTML Lite** lista os posts com template e qual template cada um usa.

### Casos negativos (inspect → view-source)

- [ ] Adicione temporariamente `{{loop post_type="htl_template"}}...{{/loop}}` num template: a página pública mostra `<!-- htl: templates não podem ser listados em {{loop}} -->` no lugar.
- [ ] Adicione `{{meta:_wp_page_template}}`: sai `<!-- htl: campos protegidos (prefixados com _) não são expostos -->`.
- [ ] Post protegido por senha: como visitante anônimo, o singular pede senha (formulário nativo); **em um `{{loop}}`** que o inclua, `{{post_content}}` mostra o formulário de senha — nunca o conteúdo.
- [ ] Excluir/despublicar o Kit Header: as páginas **não quebram** — o `{{include}}` vira comentário HTML e o resto do template renderiza.

### Cleanup esperado

- [ ] Ferramentas → Plugins → **Excluir** o plugin: posts `htl_template`, meta keys e as options `htl_archive_templates`/`htl_singular_rules` somem; as pastas em `uploads/htl-templates/` são **mantidas** (arquivos do usuário).
