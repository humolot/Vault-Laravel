# GBX Vault

Gerenciador de senhas multiusuário com grupos — Laravel 12 + SQLite, Bootstrap 5 (CDN) + jQuery, tema escuro/claro e idiomas PT-BR, ES e EN.

## Instalação (aaPanel / Apache, DocumentRoot na raiz do projeto)

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # já vem vazio no pacote
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www:www storage bootstrap/cache database
```

Requisitos PHP 8.2+: `sodium`, `pdo_sqlite`, `openssl`, `mbstring`, `fileinfo`.

O `.htaccess` da raiz encaminha tudo para `public/` e bloqueia `.env`, `database/`, `storage/` etc.

Abra o site: na primeira visita aparece a **Instalação** para criar o administrador.

> Testando sem HTTPS? Em `.env`: `SESSION_SECURE_COOKIE=false` e `FORCE_HTTPS=false`. Passkeys exigem HTTPS (ou `localhost`).

## Login em camadas

1. E-mail + **senha**
2. **Frase de segurança**
3. **2FA TOTP obrigatório** (configurado no primeiro login; gera 8 códigos de recuperação)
4. **Passkey** (opcional — o usuário cadastra no perfil e pode torná-la obrigatória)

Bloqueio após 5 erros (15 min), limite por IP, cofre trava após 15 min de inatividade, sem "lembrar-me".

## Modelo de criptografia

- Cada usuário tem um par de chaves X25519. A chave privada é cifrada com uma chave derivada (Argon2id) de **senha + frase**. O servidor nunca guarda isso em claro.
- Cada item tem chave própria (XSalsa20-Poly1305); essa chave é selada para o dono, para cada usuário e para cada grupo com acesso.
- Cada grupo tem par de chaves próprio, selado para cada membro.
- Durante a sessão, a chave privada fica cifrada na sessão com uma chave que vive apenas num cookie httpOnly do navegador — dump do banco/sessões não abre o cofre.
- Nome, etiquetas, usuário, senha, segredos, telefones e anexos (PDF etc.) são todos cifrados. Só tipo e datas ficam em claro.
- **Chave de recuperação**: exibida uma única vez ao ativar a conta. Sem senha/frase e sem essa chave, os itens que só aquele usuário acessava são irrecuperáveis (o admin pode redefinir a conta; itens compartilhados voltam a ficar acessíveis).
- `APP_KEY` cifra cookies, sessão e os segredos TOTP. Faça backup do `.env` e do `database/database.sqlite` + `storage/app/private/vault/`.

## Funções

- Tipos: Login, Códigos 2FA, Frase de recuperação, Telefone/SMS, Servidor/SSH, Chave de API, Wi-Fi, Nota segura, Outro
- Campos: Nome, Tipo, Etiqueta, Usuário/E-mail, Senha, URL, segredo (multilinha), telefone/operadora/serviços que usam o número, host/porta, observações
- Anexos cifrados (PDF, imagens, TXT, DOCX…) até 10 MB
- Compartilhamento com usuários ou grupos (leitura / edição), revogação
- Sugestões (apenas sugestões) de rotação de senha após X dias e revisão de acessos compartilhados; revogar acesso ou remover membro marca o item para troca
- Histórico das últimas 5 senhas, gerador de senha, medidor de força, cópia com limpeza automática da área de transferência
- Lixeira, auditoria completa (login, visualização, cópia, download, compartilhamento)
- Convites por link (o admin nunca conhece as credenciais), reset de 2FA, reset de conta

## Comandos de emergência

```bash
php artisan vault:unlock email@dominio.com      # remove bloqueio por tentativas
php artisan vault:reset-2fa email@dominio.com   # novo 2FA no próximo login
php artisan vault:make-admin email@dominio.com
```
