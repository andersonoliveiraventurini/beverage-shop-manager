# FA Distribuidora | passo a passo da presença na internet

Levantamento de 25/10/2026. Objetivo: mais pedidos de água, bebidas e carvão em Campinas, vindos do Google e do WhatsApp, com o mínimo de trabalho para o dono.

Legenda: **[Anderson]** faz sozinho · **[Pai]** precisa do dono (documento, celular, aprovação) · **[Claude]** pode ser feito no código.

---

## Situação hoje

| Onde | Como está | Problema |
|---|---|---|
| Perfil no Google | Existe, 7 avaliações 5,0, telefone (19) 3326-7690 certo | **Não reivindicado** ("É proprietário desta empresa?"), categoria "Adega", **sem site**, sem área de entrega |
| Site | Pronto no código (vitrine em `/`) | **Fora do ar**: fa.andersonventurini.cloud dá erro de certificado; logo não aparece na vitrine |
| LinkedIn | Página "FA Destribuidora..." | Nome com erro de digitação |
| Busca | Concorrentes com nome parecido (F & F, FAMA, WG) | O que o povo procura: "disk água Campinas", "disk água perto de mim", "água mineral 20 litros perto de mim" |

---

## Bloco 1 | Logo e identidade no site (white label)

O projeto já é todo da FA: nome, slogan, endereço, horário e telefones saem de `config/brand.php`; as cores seguem `docs/DESIGN.md`. O logo existe em `public/images/fa-logo.svg` (e o símbolo em `fa-logo-mark.svg`), mas **só aparece no painel**, não na vitrine.

1. **[Pai]** Confirmar se o logo de `fa-logo.svg` (feito a partir de `FA Distribuidora · Print.pdf`) é o logo que ele usa na fachada, na camiseta e no carro. Se ele tiver outro (foto da placa, arquivo da gráfica), mandar o arquivo.
2. **[Anderson]** Se o logo for outro: salvar como `public/images/fa-logo.svg` (de preferência SVG; se só tiver imagem, PNG de pelo menos 512 px com fundo transparente). O painel já pega o novo arquivo sozinho.
3. **[Claude]** Na vitrine (`resources/views/home.blade.php`):
   - logo no topo, no lugar do nome em texto;
   - favicon a partir do símbolo (`fa-logo-mark.svg`);
   - imagem de prévia para compartilhar no WhatsApp (og:image 1200x630 com logo e telefone).
4. **[Claude]** Opcional: levar o caminho do logo para `config/brand.php` (`'logo' => 'images/fa-logo.svg'`), para trocar de marca só mexendo na configuração.

## Bloco 2 | Site no ar, com endereço próprio

1. **[Pai + Anderson]** Registrar o domínio no Registro.br com o CNPJ da FA (cerca de R$ 40 por ano). Sugestões, nesta ordem: `fadistribuidora.com.br`, `fadistribuidoracampinas.com.br`, `disquefa.com.br`. O domínio fica no CNPJ dele, não no da AVS.
2. **[Anderson]** Decidir onde hospedar: corrigir o certificado na Oracle (o servidor responde, mas o TLS falha) **ou** levar para o servidor openclaw da AVS, atrás do Caddy que já emite o certificado sozinho. Recomendação: openclaw, porque já tem backup, monitoramento e deploy prontos.
3. **[Anderson]** Apontar o DNS do domínio para o servidor e conferir o cadeado no navegador e no celular.
4. **[Anderson]** Decidir os preços: **mostrar** só se alguém for atualizar o catálogo no painel quando o preço mudar; **esconder** se ninguém for manter (os produtos aparecem, e o botão diz "Consulte o preço no WhatsApp"). Preço velho no site gera discussão no balcão.

## Bloco 3 | Site preparado para o Google

**[Claude]** Tudo no próprio projeto, sem mudar a parte de gestão:

1. Título: `Disk água e bebidas em Campinas | FA Distribuidora, Jardim Garcia`.
2. Descrição falando de entrega de galão de 20 litros, bebidas, gelo e carvão, com os bairros.
3. Dados estruturados de loja local (`LocalBusiness` / `Store`): endereço, telefone, horário, área de entrega, link do perfil no Google.
4. Seção "Entregamos nestes bairros", com a mesma lista do Bloco 4.
5. Mapa do Google embutido no rodapé.
6. `sitemap.xml` e `robots.txt` apontando para ele.
7. Fotos reais: fachada, balcão, estoque de galões, entrega. Nada de banco de imagens.
8. Botão de ligar fixo no celular, além do WhatsApp; ajuda o público mais velho.
9. Medir o tempo de carregamento no celular (meta: abrir em menos de 2 segundos).

## Bloco 4 | Perfil da Empresa no Google

### 4.1 Reivindicar (primeiro de tudo)

1. **[Pai]** Entrar em business.google.com com o Gmail **dele** (ou um Gmail da loja, nunca o seu como único dono).
2. **[Pai]** Procurar "FA Distribuidora de Água, Bebidas e Carvão" e clicar em "Reivindicar".
3. **[Pai]** Verificar: o Google costuma pedir **vídeo** mostrando fachada, placa, balcão e algo que prove o negócio (maquininha, nota, chave abrindo a porta). Gravar com o celular, de dia, em uma tomada só.
4. **[Pai]** Depois de verificado, adicionar o Anderson como **administrador** (Perfil → Pessoas e acesso). Assim você faz o resto sem a senha dele.

### 4.2 Bairros de entrega (sim, é possível)

O Google permite **manter o endereço visível e também cadastrar a área de entrega**, até **20 locais** (bairros, cidades ou CEPs).

1. **[Anderson]** Tirar a lista real dos bairros com mais entregas do próprio sistema. No servidor de produção:

   ```bash
   php artisan tinker --execute='
   \App\Models\CustomerAddress::query()
       ->selectRaw("district, count(*) as total")
       ->whereNotNull("district")
       ->groupBy("district")->orderByDesc("total")
       ->limit(25)->get()
       ->each(fn ($r) => print("{$r->district}: {$r->total}\n"));'
   ```

2. **[Pai]** Conferir a lista: até onde ele entrega de verdade e se cobra taxa diferente em algum bairro.
3. **[Anderson]** No perfil: Editar perfil → Local e áreas → **Área de atendimento** → digitar cada bairro e escolher a sugestão que o Google mostra (ex.: "Jardim Garcia, Campinas - SP"). Se um bairro não aparecer na sugestão, use o CEP dele. Feche com "Campinas, SP" como último item.
4. Não colocar bairros onde não entrega: o Google mostra a loja para quem está lá, e um pedido recusado vira avaliação ruim.

### 4.3 O resto do perfil

1. **Categoria principal:** "Distribuidora de água". **Extras:** "Loja de bebidas", "Fornecedor de carvão", "Fornecedor de gelo" (se vender gelo). Tirar "Adega" como principal.
2. **Site:** o domínio novo com rastreio: `https://DOMINIO/?utm_source=google&utm_medium=organic&utm_campaign=perfil`.
3. **WhatsApp e telefone:** fixo (19) 3326-7690 como principal; celular/WhatsApp (19) 98177-8284 como adicional e no botão de chat.
4. **Horário:** seg a sáb 8h às 18h, dom e feriado 8h às 12h. Cadastrar os feriados como horário especial, sempre antes de cada um.
5. **Descrição** (até 750 caracteres), por exemplo:
   > Distribuidora de água mineral, bebidas, gelo e carvão no Jardim Garcia, em Campinas. Entregamos galão de 20 litros, fardos de água, refrigerantes, cervejas e carvão nos bairros da região, com pedido pelo WhatsApp ou pelo telefone. Também atendemos no balcão, na Av. Transamazônica, 1197. Aceitamos dinheiro, PIX e cartão.
6. **Atributos:** entrega, retirada na loja, PIX, cartão de débito e crédito.
7. **Produtos:** um por item mais vendido (galão 20 L, fardo de água 500 ml, carvão, gelo, refrigerante 2 L), com foto e preço só se for mantido.
8. **Fotos:** fachada de dia, placa, balcão, estoque, caminhão ou moto de entrega, o dono atendendo (se ele quiser). Uma foto nova por mês.
9. **Posts:** um por semana ou por data (calor, feriado, churrasco de fim de semana: "carvão e gelo com entrega hoje").

### 4.4 Avaliações

1. **[Anderson]** A sua avaliação no perfil vai contra a regra do Google (parente do dono). O ideal é apagar; se o Google notar, pode remover outras junto.
2. **[Pai]** Pedir avaliação aos clientes fiéis, com o link curto do perfil (Perfil → "Pedir avaliações"). Imprimir um QR Code com o link e colar no balcão e no galão entregue.
3. **[Pai ou Anderson]** Responder toda avaliação, boa ou ruim, em até 2 dias.
4. Nunca oferecer desconto ou brinde em troca de avaliação.

## Bloco 5 | Outros lugares

1. **[Anderson]** Bing Places: importar o perfil do Google (mesmo processo feito para a AVS).
2. **[Pai]** Apple Business: opcional, para aparecer no Apple Maps.
3. **[Anderson]** LinkedIn: corrigir "Destribuidora" ou fechar a página, se ninguém for usar.
4. **[Pai]** WhatsApp Business no celular da loja: catálogo com os mesmos produtos, mensagem de ausência fora do horário e endereço no perfil.
5. **[Anderson]** Guias locais gratuitos (Campinas Fácil, por exemplo): mesmo nome, endereço e telefone em todos.

## Bloco 6 | Prova social para a AVS

1. **[Pai]** Autorização por escrito (uma mensagem de WhatsApp guardada já basta) para a AVS mostrar o site como trabalho entregue.
2. **[Anderson]** No site da AVS: nome da loja, print do site e, se ele topar, um depoimento curto. Sem dizer que é seu pai e sem prometer resultado que não foi medido.
3. **[Anderson]** Depois de 60 dias no ar, anotar números reais (pedidos pelo WhatsApp vindos do site, ligações e visualizações do perfil no Google) para contar o caso com dados.

---

## Ordem sugerida

| Semana | O quê |
|---|---|
| 1 | Reivindicar o perfil (4.1), registrar o domínio, decidir sobre os preços, confirmar o logo |
| 2 | Site no ar com logo e ajustes para o Google (Blocos 1 a 3); perfil completo (4.3) |
| 3 | Bairros de entrega (4.2), fotos, QR Code de avaliação, Bing e WhatsApp Business |
| Todo mês | 1 foto nova, posts semanais, responder avaliações, olhar as visualizações do perfil |
