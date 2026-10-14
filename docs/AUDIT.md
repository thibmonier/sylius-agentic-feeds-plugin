# Audit qualité — sylius-agentic-feeds-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`.

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit` | ✅ 51 tests, 1456 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 27 fichiers avec suggestions |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 packages abandonnés (transitifs) |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 27 fichiers signalés relèvent principalement de la règle `LocallyCalledStaticMethodToNonStaticRector` — ce sont des **suggestions de modernisation non bloquantes**, non appliquées. Lancer `make rector-fix` pour les appliquer puis relancer ECS/PHPStan/tests.

Les 2 packages abandonnés (`behat/transliterator`, `php-http/message-factory`) sont **transitifs** (via `sylius/sylius`) et ne présentent aucune vulnérabilité CVE connue.

## 2. Audit qualitatif (revue par agents)

> Note : les 3 agents de revue ont été lancés en parallèle mais n'ont pas pu remettre leurs rapports dans la fenêtre d'exécution impartie. Les scores ci-dessous sont établis par l'orchestrateur à partir de l'inspection directe des fichiers clés (`FeedGenerator`, `FeedDownloadController`, `GenerateFeedController`, `ProductFeedRepository`, `TmonierSyliusAgenticFeedsExtension`, `CLAUDE.md`) et des résultats des outils automatisés.

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | `declare(strict_types=1)` + classes `final` généralisées, propriétés `readonly` en constructeur, interfaces pour storage/format/repository. PHPStan level max à 0 erreur. 27 méthodes statiques locales à convertir (Rector). |
| Intégration Symfony / Sylius | **8/10** | Extension DI propre avec deps optionnelles (outbox, scheduler, flysystem) via `interface_exists`. CSRF sur l'action admin. Messenger correctement intégré. `PrependDoctrineMigrationsTrait` utilisé. |
| Sécurité (OWASP 2025) | **8/10** | `hash_equals` pour les tokens de flux privés, 404 systématique sur flux introuvable/désactivé (pas de fuite d'info), CSRF sur l'action admin de génération, DQL entièrement paramétré, `X-Robots-Tag: noindex` sur les flux. |

### Points forts
- Typage strict partout : `declare(strict_types=1)`, `final`, `readonly`, PHPStan max à 0 erreur.
- `FeedDownloadController` : `hash_equals` pour la comparaison de token, 404 sur tous les échecs (pas de 401/403 qui fuitent l'existence du flux), cache-headers HTTP corrects, `X-Robots-Tag: noindex`, réponse streamed sans chargement en mémoire.
- `GenerateFeedController` : CSRF paramétré par entité (`tmonier_agentic_feeds_generate_{id}`), dispatch asynchrone via Messenger, flash message i18n.
- DI extension : gestion explicite des bridges optionnels (`symfony/scheduler`, `tmonier/sylius-webhook-outbox-plugin`) via `interface_exists` — pas de dépendance hard-codée dans `require`.
- `FeedGenerator` : clear d'EntityManager entre batches, temp streams → atomic rename via storage, issues plafonnées à 5000, architecture génération/validation/stockage séparée.
- Repository DQL 100 % paramétré via QueryBuilder.

## 3. Recommandations priorisées

### 🟠 Majeur
1. **27 méthodes statiques locales à convertir** (signalées par `LocallyCalledStaticMethodToNonStaticRector`). Des méthodes privées/protégées déclarées `static` sans nécessité sont un odeur de conception ; elles couplent à la classe concrète et empêchent l'override dans les tests. → Lancer `make rector-fix`, relancer ECS + PHPStan + tests.
2. **`ProductFeedRepository` non-final** (`src/Repository/ProductFeedRepository.php:10`). Seul repository du plugin à ne pas être `final` (la classe hérite `EntityRepository` et est publique via l'interface, mais la classe concrète devrait être `final` pour éviter l'héritage de l'infrastructure). → Ajouter `final`.
3. **Pas de vérification de sécurité Symfony dans les controllers admin** (`src/Controller/Admin/GenerateFeedController.php`). L'accès admin repose uniquement sur la configuration du pare-feu Sylius (routes sous `/admin`). Si le plugin est installé dans une app avec un firewall plus permissif, l'action est exposée. → Ajouter `$this->denyAccessUnlessGranted('ROLE_ADMINISTRATION_ACCESS')` ou un voter équivalent.
4. **Packages abandonnés transitifs** (`behat/transliterator`, `php-http/message-factory`). Aucune CVE connue, mais `php-http/message-factory` est supplanté par `psr/http-factory`. → Surveiller les advisories ; envisager de forcer `psr/http-factory` en remplacement si une CVE survient.

### 🟡 Mineur
- `FeedGenerator` : 9 paramètres de constructeur (`src/Feed/FeedGenerator.php:40-49`). Acceptable pour un générateur central, mais envisager un objet de contexte groupant `itemBuilder` + `formats` + `batchSize` pour alléger la signature.
- `FeedGenerator::generate()` : méthode de ~120 lignes avec une boucle imbriquée et gestion d'exceptions. Complexité cyclomatique élevée ; extraire `buildItem()` + `flushStreams()` en méthodes privées.
- `markStale()` dans le repository fait un UPDATE bulk sans journalisation (`src/Repository/ProductFeedRepository.php:28-36`). Ajouter un log info avec le nombre de flux marqués stale.
- Appliquer les 27 suggestions Rector (`make rector-fix`) avant la prochaine release.

### ℹ️ Pour information (choix de conception assumés)
- Les flux publics (sans `accessToken`) sont volontairement indexables par Google (pas de `X-Robots-Tag: noindex` pour eux) — comportement cohérent avec l'usage GMC.
- La règle `no markTestSkipped` dans `CLAUDE.md` impose des fixtures déterministes dans `TestCatalog` — bonne pratique, pas de TODO à traiter.
- Les dépendances `symfony/scheduler` et `tmonier/sylius-webhook-outbox-plugin` restent en `require-dev` uniquement, conformément à la règle CLAUDE.md.

## 4. Verdict

Plugin **mature et discipliné** : outillage au vert (ECS, PHPStan level max, 51 tests unitaires / 1456 assertions), typage strict, architecture multicouche propre (génération / format / validation / stockage), sécurité du contrôleur de téléchargement exemplaire (`hash_equals`, 404 systématique, CSRF). Les axes d'amélioration prioritaires sont **la conversion des méthodes statiques locales** (27 fichiers Rector), **le final manquant sur `ProductFeedRepository`** et **l'ajout d'un check de rôle explicite dans le controller admin** — rien de bloquant pour une beta, mais à corriger avant une release stable grand public.
