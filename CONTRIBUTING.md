# Contributing to the Grafana plugin for GLPI

## Development setup

After cloning the repository, install dependencies and configure git hooks:

```bash
composer install
composer run setup-dev
```

`setup-dev` sets `core.hooksPath` to `.git-hooks/` and makes the pre-commit hook executable. The pre-commit hook runs phpcs and phpstan before each commit.

## Branches

| Branch | Purpose |
|---|---|
| `main` | Stable releases, targets GLPI 11 |
| `dev` | Integration branch — features and fixes merge here before `main` |
| `support/glpi10` | Maintenance branch for GLPI 10 |

We follow a [git flow](https://nvie.com/posts/a-successful-git-branching-model/) style workflow: new features and bugfixes live in short-lived branches off `dev` and are merged back via pull request. Using git flow is recommended but not required.

## Coding standards

PHP CS Fixer and PHPStan run automatically on every commit via the pre-commit hook. To run them manually:

```bash
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

## Creating a release

1. Merge all changes to `main` (or `support/glpi10` for a GLPI 10 release)
2. Create and push a tag — GitHub Actions builds the zip automatically:
   ```bash
   git tag 1.x.y
   git push origin 1.x.y
   ```
3. Update `grafana.xml` on `main` with the new version entry and push

## For distributors

If you are repackaging this plugin, install dependencies without running lifecycle scripts:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
```

---

# Contribuir al plugin de Grafana para GLPI

## Configuración del entorno de desarrollo

Después de clonar el repositorio, instalar las dependencias y configurar los git hooks:

```bash
composer install
composer run setup-dev
```

`setup-dev` establece `core.hooksPath` en `.git-hooks/` y hace ejecutable el hook de pre-commit. El hook ejecuta phpcs y phpstan antes de cada commit.

## Ramas

| Rama | Propósito |
|---|---|
| `main` | Releases estables, apunta a GLPI 11 |
| `dev` | Rama de integración — features y fixes se mergean aquí antes de `main` |
| `support/glpi10` | Rama de mantenimiento para GLPI 10 |

Seguimos un flujo de trabajo estilo [git flow](https://nvie.com/posts/a-successful-git-branching-model/): las nuevas funcionalidades y correcciones viven en ramas de corta duración desde `dev` y se mergean de vuelta mediante pull request. Usar git flow es recomendable pero no obligatorio.

## Estándares de código

PHP CS Fixer y PHPStan corren automáticamente en cada commit a través del hook de pre-commit. Para correrlos manualmente:

```bash
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

## Crear un release

1. Mergear todos los cambios a `main` (o `support/glpi10` para un release de GLPI 10)
2. Crear y pushear un tag — GitHub Actions genera el zip automáticamente:
   ```bash
   git tag 1.x.y
   git push origin 1.x.y
   ```
3. Actualizar `grafana.xml` en `main` con la nueva entrada de versión y pushear

## Para distribuidores

Si estás reempaquetando este plugin, instalá las dependencias sin ejecutar los lifecycle scripts:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
```
