# Documentação do sistema — Dogão Lanches

> **Nota desta revisão:** este documento foi revisado a partir do `restaurante.sql` real em uso. Principais correções: o modelo de pedidos já está unificado (`pedidos` + `pedido_itens`, não existe mais `comandapedidos`); a mesa é selecionada a partir do cadastro em `mesas`, não digitada livremente; e a estrutura de bloqueio de conta por tentativas falhas já existe no banco. Detalhes de cada ponto estão marcados abaixo como **⚠ Confirmar**.

## O que este projeto faz

Este é um painel interno para um restaurante. Nele, uma pessoa autorizada pode:

- entrar com e-mail e senha;
- consultar pedidos por mesa e o estado de pagamento;
- registrar e atualizar pedidos pela recepção;
- enviar pedidos de mesa para o caixa e a cozinha pelo garçom;
- acompanhar a fila de preparo e finalizar pedidos pela cozinha;
- cadastrar e manter os pratos disponíveis no cardápio;
- visualizar e alterar os e-mails da equipe no painel;
- administrar os funcionários, caso seja administrador.

## Caminho principal do usuário

```text
public/index.php
        ↓
templates/login.php ──(envia e-mail e senha)──> auth.php
        ↑                                        ↓
        └─────── erro de login             templates/painel.php
                                                    ├── templates/pedidos.php (acesso por perfil)
                                                    ├── templates/cozinha.php (somente cozinheiro)
                                                    ├── templates/garcom.php (garçom, gerente e admin)
                                                    ├── templates/cardapio.php (gerente e admin)
                                                    ├── atualizar e-mail
                                                    ├── templates/crud.php (somente admin)
                                                    └── logout.php
```

Para acessar diretamente, use `templates/login.php`. O arquivo `public/index.php` apenas redireciona para essa tela.

## Responsabilidade de cada arquivo

| Arquivo | Responsabilidade | Usado no fluxo atual? |
| --- | --- | --- |
| `config/conexao.php` | Define a URL base do projeto e cria a conexão PDO com o MySQL. A variável `$conn` é usada nas consultas ao banco. | Sim |
| `src/Auth.php` | Centraliza sessão, login, logout, validação de e-mail, controle de acesso e hash de senha. | Sim |
| `auth.php` | Recebe o formulário de login, chama `Auth::login()` e envia recepção, cozinha e garçom diretamente para suas respectivas telas; os demais perfis seguem para o painel. | Sim |
| `logout.php` | Chama o logout: limpa os dados da sessão, remove o cookie e redireciona ao login. | Sim |
| `templates/login.php` | Exibe o formulário de acesso. Com sessão válida, envia a recepção aos pedidos e os demais perfis ao painel. | Sim |
| `templates/painel.php` | Tela principal. Exige login, mostra a equipe e permite alterar e-mails. Para pedidos, direciona os perfis permitidos à tela própria. | Sim |
| `templates/pedidos.php` | Tela de pedidos: administrador, gerente e recepção cadastram, editam e excluem. | Sim |
| `templates/cozinha.php` | Tela exclusiva do cozinheiro, com a fila diária de preparo e a opção de marcar cada pedido como pronto. | Sim |
| `templates/garcom.php` | Tela para registrar mesa e itens, enviando o pedido ao caixa e à cozinha. Garçom, gerente e administrador podem acessá-la. | Sim |
| `templates/cardapio.php` | Cadastro e manutenção dos pratos disponíveis. Gerente e administrador podem acessá-la. | Sim |
| `src/Cardapio.php` | Cria a estrutura de pratos e dos itens vinculados a cada pedido. | Sim |
| `src/Pedidos.php` | Cria/atualiza a estrutura da tabela `pedidos` e define os status de pagamento aceitos. | Sim |
| `templates/crud.php` | Área exclusiva de administradores para criar, buscar, editar, ativar/desativar e excluir funcionários. | Sim |
| `public/index.php` | Atalho de entrada que redireciona para o login. | Sim |
| `public/css/style.css` | Estilos da tela de login, painel e cadastro de funcionários, incluindo adaptação para celular. | Sim |
| `restaurante.sql` | Estrutura inicial e usuários de exemplo do banco de dados. | Sim, na instalação |
| `.htaccess` | Bloqueia acesso web direto às pastas `config/` e `src/` e envia alguns cabeçalhos de segurança. | Sim, se o Apache permitir `.htaccess` |
| `templates/index.php` | Redireciona links antigos para `painel.php`. | Sim, como compatibilidade |
| `templates/admin.css` | Folha de estilo antiga, atualmente não carregada. | Não |
| `public/telalogin.css` | Folha de estilo antiga que não é carregada pelas telas atuais. | Não |
| `.vscode/settings.json` | Preferências locais do editor VS Code. Não interfere no sistema em produção. | Não |

## Autenticação e permissões

### Sessão

Depois de um login válido, `src/Auth.php` guarda estes dados em `$_SESSION`:

| Chave | Conteúdo |
| --- | --- |
| `usuario_id` | ID do usuário no banco (`users_login.id`) |
| `usuario` | Nome exibido no sistema (`users_login.username`) |
| `usuario_email` | E-mail do usuário |
| `usuario_role` | Perfil de acesso |

O ID da sessão é renovado após o login para reduzir o risco de fixação de sessão. O cookie da sessão é `httponly` e usa `SameSite=Lax`.

### Perfis

| Valor em `role` | Perfil | Acesso atual |
| --- | --- | --- |
| `1` | Administrador | Painel e cadastro completo de funcionários |
| `2` | Gerente | Painel e consulta de pedidos |
| `3` | Recepção | Entra diretamente em pedidos; consulta, cadastra e edita pedidos |
| `4` | Cozinheiro | Entra diretamente na cozinha e atualiza o status de preparo dos pedidos do dia |
| `5` | Garçom | Entra diretamente na tela de pedidos e envia pedidos para o caixa e a cozinha |

`Auth::requireLogin()` bloqueia quem não está logado. `Auth::requireAdmin()` usa essa verificação e também bloqueia quem não tem `role = 1`. A tela de pedidos é restrita a administrador, gerente e recepção, e os três perfis podem alterar pedidos. A tela de cozinha pode ser usada por cozinheiro, gerente e administrador. A tela do garçom pode ser usada por garçom, gerente e administrador. O cardápio pode ser mantido por gerente e administrador.

## Funcionalidades por tela

### Login — `templates/login.php`

O formulário envia `email` e `senha` por `POST` para `auth.php`. A senha enviada nunca é comparada diretamente com o banco: `Auth::login()` usa `password_verify()` contra `users_login.password_hash`.

**⚠ Confirmar:** o schema reserva `users_login.failed_attempts` e `users_login.locked_until` para bloqueio de conta após tentativas malsucedidas. Vale confirmar se `Auth::login()` já incrementa/verifica esses campos ou se essa lógica ainda precisa ser implementada.

### Painel — `templates/painel.php`

Ao abrir, o painel:

1. confirma que existe uma sessão ativa;
2. busca usuários e a quantidade de usuários ativos;
3. mostra o valor dos pedidos pagos no dia e o atalho para pedidos somente para administrador, gerente e recepção.

No painel, a ação `POST` disponível é salvar e-mail (`usuario_id`, `email`), que atualiza `users_login.email`.

### Pedidos — `templates/pedidos.php`

A tela separada controla os pedidos por mesa e mostra os totais acumulados de hoje, separados em pago e a receber. Administrador, gerente e recepção podem registrar, editar e excluir pedidos.

| Campo | Finalidade |
| --- | --- |
| Mesa | Selecionada a partir das mesas cadastradas em `mesas` (hoje 12 mesas, numeradas de 1 a 12, com capacidades entre 2 e 8 lugares) — não é mais um número livre digitado. |
| Status de pagamento | `Ainda não pago` ou `Pago`. |

**⚠ Confirmar:** `pedidos` não tem coluna de valor total. O total precisa vir da soma de `pedido_itens.quantidade × pedido_itens.preco_unitario`, ou a tela de recepção ainda depende de um campo "Valor" que não existe mais na tabela atual. Vale checar como `pedidos.php` calcula/grava esse valor hoje.

Administrador e gerente veem a opção **Zerar o dia**. Ela exclui todos os pedidos registrados na data atual, pagos e pendentes, apenas depois de validar a senha da conta que está logada.

O número mostrado na lista de pedidos é uma sequência visual diária: começa em `#1` a cada data e, depois de zerar o dia, o próximo pedido volta a aparecer como `#1`. O ID interno do banco (`pedidos.id`) permanece único para evitar conflitos entre dias diferentes.

### Cozinha — `templates/cozinha.php`

O cozinheiro vê apenas os pedidos criados no dia atual, em ordem de preparo: os que estão em preparo aparecem antes dos prontos. Cada item mostra número do pedido, mesa, horário de recebimento e estado de pagamento. O botão **Marcar como pronto** atualiza `pedidos.status_preparo`; se necessário, **Voltar ao preparo** desfaz a marcação.

### Garçom — `templates/garcom.php`

O garçom seleciona a mesa (a partir de `mesas`) e as quantidades dos pratos disponíveis em `produtos`. O total é calculado a partir dos preços cadastrados, e cada item vira uma linha em `pedido_itens` com o preço congelado no momento da venda. Ao enviar, o pedido é salvo como pagamento pendente e preparo aguardando, aparecendo imediatamente no caixa e na fila da cozinha com os itens que devem ser preparados.

### Cardápio — `templates/cardapio.php`

Gerente e administrador podem cadastrar pratos com nome, descrição, preço, tipo (`tipos_produto`) e categoria (`categorias_produto`), além de editar ou marcar um prato como indisponível (`produtos.ativo`). Itens indisponíveis permanecem no histórico de pedidos, mas não podem ser selecionados pelo garçom.

### Cadastro de funcionários — `templates/crud.php`

Somente administradores entram nesta página. Ela trabalha exclusivamente com `users_login`.

| Ação | O que acontece |
| --- | --- |
| Cadastrar | Valida nome, e-mail, senha e perfil; verifica e-mail e nome de usuário repetidos; salva a senha com hash. |
| Buscar | Filtra por nome ou e-mail usando `busca` na URL. |
| Editar | Altera nome, e-mail, perfil, status e, se preenchida, a senha. |
| Ativar/desativar | Alterna `is_active`. |
| Excluir | Remove outro usuário; o administrador logado não pode apagar a própria conta. |

Quando o administrador muda o próprio nome ou perfil, os valores equivalentes da sessão são atualizados imediatamente.

## Banco de dados

A imagem abaixo mostra o diagrama entidade-relacionamento (DER) do banco, evidenciando como as principais entidades do sistema se conectam:

![DER do banco de dados do restaurante](img/saida_page-0001.jpg)

### Tabelas usadas hoje pelo PHP

| Tabela | Colunas principais | Quem usa | Finalidade |
| --- | --- | --- | --- |
| `users_login` | `username`, `email`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until` | `Auth.php`, `painel.php`, `crud.php` | Contas, senhas com hash, perfis, status de acesso e controle de tentativas de login. |
| `mesas` | `numero`, `capacidade`, `status`, `hora_reserva`, `reservado_por`, `tel_reserva` | `pedidos.php`, `garcom.php` | Cadastro de mesas físicas. Os campos de reserva existem no banco, mas ainda não têm tela própria. |
| `pedidos` | `mesa_id` (FK), `status_pagamento`, `status_preparo`, `criado_em` | `pedidos.php`, `cozinha.php`, `garcom.php`, `Pedidos.php` | Cabeçalho do pedido: mesa, status de pagamento e de preparo. Não guarda valor total diretamente. |
| `produtos` | `nome`, `tipo_id` (FK), `categoria_id` (FK), `preco`, `descricao`, `ativo` | `cardapio.php`, `garcom.php`, `Cardapio.php` | Pratos, classificação por tipo/categoria, preços e disponibilidade. |
| `pedido_itens` | `pedido_id` (FK), `produto_id` (FK), `quantidade`, `preco_unitario` | `garcom.php`, `cozinha.php`, `pedidos.php`, `Cardapio.php` | Itens de cada pedido, com preço congelado no momento da venda — é daqui que vem o valor total do pedido. |
| `categorias_produto` / `tipos_produto` | `nome` | `cardapio.php` | Tabelas de referência usadas na classificação dos pratos. |
| `login_audit` | `user_id` (FK), `ip_address`, `success`, `reason` | Criada pelo SQL; ainda não amplamente usada pelo PHP atual | Estrutura pronta para auditoria de tentativas de login. |

### Modelo de pedidos

O sistema já usa um único modelo, em formato cabeçalho/detalhe: `pedidos` guarda a mesa e os status, e `pedido_itens` guarda cada prato pedido, com quantidade e preço no momento da venda. Não existe mais uma tabela paralela de "comanda" — se esse nome aparecer em código ou em versões antigas da documentação, refere-se a este mesmo par de tabelas.

## Proteções já presentes

- PDO com consultas preparadas nas partes que recebem dados do usuário;
- `password_hash()` e `password_verify()` para senhas;
- estrutura de bloqueio de conta por tentativas falhas (`failed_attempts`, `locked_until` em `users_login`) — **⚠ confirmar se já está sendo aplicada em `Auth::login()`**;
- validação de e-mail, ID e valores antes das gravações;
- `htmlspecialchars()` ao imprimir dados variáveis na tela;
- página de funcionários protegida para administradores;
- pastas com código interno bloqueadas pelo `.htaccess`.

## Pontos importantes para manutenção

- **Edição de e-mail sem restrição:** o painel permite que qualquer usuário autenticado altere o e-mail de qualquer conta listada. Isso é o comportamento atual de `painel.php` e é uma falha de controle de acesso a corrigir — só administrador (ou o próprio usuário) deveria poder editar e-mail de conta.
- **Valor do pedido:** `pedidos` não tem coluna de total; o valor exibido em `pedidos.php` provavelmente vem de uma soma sobre `pedido_itens`. Vale confirmar essa lógica antes de qualquer alteração no schema.
- **Mesas com dados de reserva ociosos:** `mesas` já tem `status`, `hora_reserva`, `reservado_por` e `tel_reserva`, mas nenhuma tela usa esses campos hoje. É um recurso pronto no banco, mas não implementado na interface.
- **Erro de digitação nos dados semente:** o `restaurante.sql` cadastra `recepcao@email.comm` (com "m" duplicado). Vale corrigir esse e-mail no script antes de reimportar o banco.
- `templates/index.php` foi mantido apenas para redirecionar links antigos ao painel atual.
- As credenciais do MySQL estão em `config/conexao.php`. Em produção, use usuário próprio do banco e uma senha forte.

## Como executar localmente

1. Inicie Apache e MySQL no XAMPP.
2. Configure as credenciais de banco em `config/conexao.php` se necessário.
3. Crie o banco e importe `restaurante.sql` (a criação do banco `restaurante` já está incluída no script).
4. Abra `http://localhost/restauranteSteelTeam/templates/login.php`.

O usuário inicial definido no SQL é `admin@email.com`, perfil administrador.