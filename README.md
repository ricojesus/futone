# 🏆 Futone - Sistema de Gerenciamento de Partidas

Futone é uma plataforma web moderna para gerenciamento de partidas de futebol/futsal. O sistema permite criar, organizar e acompanhar partidas, equipes e resultados de forma simples e intuitiva.

## 📋 Características Principais

- 👥 **Autenticação de Usuários**: Sistema completo de login, registro e gerenciamento de perfil
- ⚽ **Gerenciamento de Partidas**: Criar, editar e acompanhar partidas em tempo real
- 👨‍👩‍👧‍👦 **Gerenciamento de Equipes**: Organizar e gerenciar equipes e seus integrantes
- 📊 **Motor de Matchmaking**: Sistema inteligente para definir partidas equilibradas
- 🔐 **Sistema Seguro**: Autenticação com tokens e autorização por papel de usuário
- 📱 **Interface Responsiva**: Design moderno com Tailwind CSS

## 🛠️ Stack Tecnológico

- **Backend**: Laravel 11.x
- **Frontend**: Blade Templates + Alpine.js
- **Styling**: Tailwind CSS
- **Database**: SQLite (desenvolvimento)
- **Testing**: Pest Framework
- **Build Tool**: Vite

## 🚀 Começando

### Requisitos
- PHP 8.2+
- Composer
- Node.js 18+
- npm

### Instalação

1. **Clone o repositório**
```bash
git clone <seu-repositorio>
cd futone
```

2. **Instale as dependências PHP**
```bash
composer install
```

3. **Configure o arquivo .env**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Instale as dependências Node.js**
```bash
npm install
```

5. **Compile os assets**
```bash
npm run build
```

6. **Execute as migrações**
```bash
php artisan migrate
```

7. **Inicie o servidor de desenvolvimento**
```bash
php artisan serve
```

A aplicação estará disponível em `http://localhost:8000`

## 📁 Estrutura do Projeto

```
futone/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Controladores da aplicação
│   │   └── Controllers/Auth/   # Controladores de autenticação
│   ├── Models/                 # Modelos Eloquent
│   └── Services/               # Serviços de negócio
├── resources/
│   ├── views/                  # Templates Blade
│   ├── css/                    # Estilos Tailwind
│   └── js/                     # JavaScript Alpine.js
├── routes/
│   ├── web.php                 # Rotas web
│   ├── api.php                 # Rotas API
│   └── auth.php                # Rotas de autenticação
├── database/
│   ├── migrations/             # Migrações do banco
│   ├── factories/              # Model factories para testes
│   └── seeders/                # Seeders do banco
└── tests/                      # Testes da aplicação
```

## 🔑 Funcionalidades Principais

### Autenticação
- Registro de novos usuários
- Login seguro
- Recuperação de senha
- Perfil de usuário customizável

### Gerenciamento de Partidas
- Criar novas partidas com data, hora e local
- Definir equipes participantes
- Registrar placar e resultado
- Histórico completo de partidas

### Gerenciamento de Equipes
- Criar e gerenciar equipes
- Adicionar/remover jogadores
- Visualizar estatísticas da equipe
- Histórico de participações

### Motor de Matchmaking
- Sugestão automática de equipes equilibradas
- Análise de histórico de desempenho
- Previsão de resultados

## 📝 Rotas Principais

| Rota | Descrição |
|------|-----------|
| `/` | Página inicial |
| `/login` | Login |
| `/register` | Registro de novo usuário |
| `/dashboard` | Dashboard do usuário (autenticado) |
| `/profile` | Perfil do usuário |
| `/matches` | Listagem de partidas |
| `/teams` | Listagem de equipes |

## 🧪 Testing

Execute os testes com:

```bash
./vendor/bin/pest
```

## 🔄 Build para Produção

```bash
npm run build
php artisan config:cache
php artisan route:cache
```

## 📄 Licença

Este projeto está sob a licença MIT.

## 👥 Contribuidores

Desenvolvido por **Lucilene de Jesus**

## 📧 Suporte

Para dúvidas ou sugestões, entre em contato através do dashboard da aplicação.

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
