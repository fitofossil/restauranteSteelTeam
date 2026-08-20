# 🍔 Restaurante Steel Team (Dogão Lanches)

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)]()
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=flat&logo=mysql&logoColor=white)]()
[![SENAI](https://img.shields.io/badge/Curso-BackEnd_SENAI--RS-red?style=flat)]()

Projeto de Desenvolvimento Web desenvolvido para o curso de Back-End do SENAI-RS. Trata-se de um sistema interno de gestão para o **Dogão Lanches**, projetado para modernizar o fluxo de atendimento, conectando o salão, o caixa e a cozinha.

## 🚀 Funcionalidades
>>>>>>> origin/develop

O sistema é dividido por perfis de acesso, garantindo segurança e organização:
* **Garçom:** Lançamento rápido de pedidos vinculados às mesas e aos itens do cardápio.
* **Cozinha:** Painel em tempo real para controle da fila de preparo (Aguardando / Pronto).
* **Caixa (Recepção):** Visão geral das comandas, fechamento de contas e recebimentos.
* **Administração:** Gestão completa de usuários, controle do cardápio e acesso a logs de auditoria.
* **Gerente:** Gestão completa do restaurante, tirando as funções de administrador.

## 🛠️ Tecnologias Utilizadas

* **Linguagem:** PHP (Paradigma procedural/misto)
* **Banco de Dados:** MySQL (MariaDB) com modelagem relacional
* **Front-End:** HTML5, CSS3 
* **Servidor Local:** Apache (via XAMPP)

## ⚙️ Instalação e Início Rápido

Siga os passos abaixo para rodar o projeto localmente em sua máquina:

1. **Clone o repositório** e coloque a pasta `restauranteSteelTeam` dentro do diretório `htdocs` do seu XAMPP.
2. Inicie os módulos **Apache** e **MySQL** no painel do XAMPP.
3. Acesse o `phpMyAdmin` (geralmente em `http://localhost/phpmyadmin`).
4. Crie um banco de dados chamado `restaurante`.
5. Importe o arquivo principal do banco de dados: [restaurante.sql](restaurante.sql).
6. *(Opcional)* Aplique a migração indicada na [documentação completa](DOCUMENTACAO.md#instalação-do-banco) se necessário.
7. Acesse o sistema pelo navegador: `http://localhost/restauranteSteelTeam/templates/login.php`

### 🔑 Acesso Inicial (Administrador)
Para o primeiro acesso após a instalação, utilize as seguintes credenciais padrão:
* **Usuário:** `admin@email.com`
* **Senha:** `administrador` *(Lembre-se de alterar a senha e/ou especificar a senha real configurada no seu hash)*

## 📚 Documentação

Para um entendimento profundo da arquitetura do projeto, consulte a nossa [DOCUMENTACAO.md](DOCUMENTACAO.md). Lá você encontrará:
- O fluxo lógico entre login, painéis e logout;
- A responsabilidade de cada arquivo/módulo;
- Regras de negócio, perfis de acesso e gestão de sessão;
- O modelo de dados (tabelas usadas e planejadas);
- Pontos de atenção para evolução e manutenção futura.

---
**Desenvolvido pela equipe Steel Team** - SENAI/RS
