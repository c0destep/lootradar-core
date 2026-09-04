# Publicação no Packagist

O pacote oficial do Core é `lootradar/lootradar`. Este roteiro separa a preparação local,
a publicação da release no GitHub e o cadastro no Packagist para que nenhum estágio seja
tratado como concluído antes da verificação correspondente.

## 1. Validar o candidato à release

1. Confirme que a branch é `main`, que o repositório está sincronizado e que a árvore está limpa.
2. Atualize a versão da CLI e o `CHANGELOG.md` no mesmo commit de preparação da release.
3. Execute o gate completo:

   ```bash
   composer check:package
   ```

4. Gere e inspecione o artefato distribuível:

   ```bash
   composer archive --format=tar --dir=/tmp --file=lootradar
   tar -tf /tmp/lootradar.tar
   ```

   O arquivo deve conter o código de runtime, `composer.json`, `LICENSE`, `.env.example`, temas
   e schemas públicos. Não pode conter `.env`, credenciais, `vendor/`, caches, testes, arquivos
   da IDE nem documentos internos de continuidade.

5. Instale o artefato em um projeto temporário e limpo, sem dependências de desenvolvimento.
   Confirme o autoload e o proxy da CLI:

   ```bash
   composer install --no-dev --prefer-dist
   vendor/bin/lootradar --version
   ```

O `composer.lock` deste repositório fixa o ambiente de desenvolvimento e os gates. Como este é
um pacote do tipo `library`, os consumidores resolvem suas próprias versões compatíveis.

## 2. Publicar a release no GitHub

Somente depois do gate verde e do commit de release:

```bash
git tag -a vX.Y.Z -m "LootRadar Core vX.Y.Z"
git push origin main
git push origin vX.Y.Z
```

Confira no GitHub se o commit e a tag apontam para o mesmo conteúdo validado. Nunca mova ou
force uma tag publicada; correções posteriores recebem uma nova versão Semantic Versioning.

## 3. Cadastrar e sincronizar o Packagist

1. Entre no Packagist com a conta mantenedora.
2. Envie a URL pública `https://github.com/c0destep/lootradar-core`.
3. Confirme que o nome detectado é exatamente `lootradar/lootradar`.
4. Habilite a integração do GitHub ou configure o hook de atualização indicado pelo próprio
   Packagist. Tokens de API pertencem ao gerenciador de segredos; nunca ao repositório.
5. Aguarde a indexação da tag e confirme que não há avisos de metadados ou dependências.

## 4. Verificação pública

Em um diretório novo, execute:

```bash
composer init --name=lootradar/package-smoke --no-interaction
composer require lootradar/lootradar:^0.4 --no-dev --prefer-dist
vendor/bin/lootradar --version
vendor/bin/lootradar help snapshot
```

Verifique também que o arquivo
`vendor/lootradar/lootradar/resources/schema/lootradar-snapshot-v1.schema.json` existe e que a
CLI lê o `.env` na raiz da aplicação consumidora. Só depois desses testes o critério
“instalável pelo Packagist” pode ser marcado como concluído no roadmap.

## 5. Releases seguintes

- Atualize `CHANGELOG.md` e a versão exibida pela CLI.
- Preserve compatibilidade conforme Semantic Versioning e versione mudanças incompatíveis do
  snapshot em um novo schema.
- Repita o gate completo, a inspeção do arquivo e a instalação limpa.
- Publique primeiro no GitHub; a integração do Packagist deve detectar a nova tag.
