# Restaurante Steel Team

Projeto de WebDev do curso de BackEnd do SENAI - RS

Sistema de gestão para o restaurante **Dogão Lanches**, com painel administrativo, cadastro de funcionários e registro de pedidos.

---

## Estrutura do Projeto

```
restauranteSteelTeam/
├── config/
│   └── conexao.php              # Conexão PDO + BASE_URL
├── public/
│   ├── index.php                # Redireciona para login.php
│   └── css/
│       └── style.css            # Estilo unificado
├── src/
│   └── Auth.php                 # Classe de autenticação
├── templates/
│   ├── login.php                # Tela de login
│   ├── painel.php               # Painel administrativo
│   └── crud.php                 # Cadastro de Funcionários
├── auth.php                     # Handler de login
├── logout.php                   # Destrói sessão
├── .htaccess                    # Proteção de pastas
├── mesa.sql                     # Schema do banco
└── README.md
```

---

## Fluxo do Sistema

```
login.php → auth.php → painel.php → logout.php
                         └── crud.php (admin only)
```

---

## Segurança

- `password_hash()` / `password_verify()` (bcrypt)
- Prepared statements com PDO (SQL injection)
- `htmlspecialchars()` em saída (XSS)
- `session_regenerate_id(true)` após login (session fixation)
- Cookies com `httponly`, `samesite=Lax`
- `.htaccess` protege `config/` e `src/`

---

## Banco de Dados

Banco: `restaurante` (MySQL, localhost:3306, root, sem senha)

### Tabela `users_login`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT, PK | Identificador |
| `username` | VARCHAR(45) | Nome |
| `email` | VARCHAR(100) | E-mail (login) |
| `password_hash` | VARCHAR(255) | Senha bcrypt |
| `role` | TINYINT | 1=Admin, 2=Gerente, 3=Funcionário |
| `is_active` | TINYINT | 1=Ativo, 0=Inativo |

### Migração

```sql
ALTER TABLE `categorias_produto` ADD PRIMARY KEY (`id`);
ALTER TABLE `categorias_produto` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tipos_produto` ADD PRIMARY KEY (`id`);
ALTER TABLE `tipos_produto` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `produtos` ADD PRIMARY KEY (`id`);
ALTER TABLE `produtos` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users_login` ADD COLUMN `role` TINYINT(1) UNSIGNED NOT NULL DEFAULT 3 AFTER `is_active`;
UPDATE `users_login` SET `role` = 1 WHERE `id` = 1;
```

---

## Como Rodar

1. Iniciar XAMPP (Apache + MySQL)
2. Importar `mesa.sql` no phpMyAdmin
3. Executar migração SQL acima
4. Acessar `http://localhost/restauranteSteelTeam/templates/login.php`
5. Login com e-mail e senha do administrador
