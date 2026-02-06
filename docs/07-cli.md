# CLI (long polling)

Для локального тестирования можно запускать long polling через `telegram:poll`.
В проде рекомендуется использовать webhook.

## Пример

```bash
php psb telegram:poll --bot=auth --debug
```

## Опции

- `--bot, -b` — имя бота из конфигурации (по умолчанию используется `default`)
- `--all, -a` — опросить всех ботов последовательно
- `--timeout, -t` — таймаут long polling (сек)
- `--sleep, -s` — пауза между запросами (сек)
- `--offset, -o` — начальный offset
- `--debug, -d` — показать ход выполнения
- `--once` — выполнить один запрос и завершиться

## Параллельный режим

`--all` запускает отдельный процесс на каждого бота (через `pcntl_fork`).
Если расширение `pcntl` недоступно — команда завершится с ошибкой.

## Регистрация webhook

Для регистрации webhook URL есть команда `telegram:webhook`.
Её обычно запускают один раз при добавлении нового бота.

```bash
php psb telegram:webhook --bot=auth --base-url=https://example.com
```

## Синхронизация и сброс команд

Команды бота из `telegram.commands.<bot>` можно применить через:

```bash
php psb telegram:sync --bot=auth
```

Если нужно зафиксировать IP, который Telegram использует для доставки webhook (например при миграции панели между серверами), используйте:

```bash
php psb telegram:sync --bot=auth --webhook --webhook-ip-address=82.26.150.109
```

Эквивалентно можно передать через окружение:

```bash
TELEGRAM_WEBHOOK_IP_ADDRESS=82.26.150.109 php psb telegram:sync --bot=auth --webhook
```

Чтобы сбросить команды в Telegram (очистить список команд бота), используйте:

```bash
php psb telegram:sync:reset --bot=auth
```

Если `--bot` не передан, `telegram:sync` и `telegram:sync:reset` пройдут по всем ботам из `telegram.bots`.

## Flow map (CJM/branch)

```bash
php psb telegram:flow-map --scope=branch --id=start.new_user
php psb telegram:flow-map --scope=cjm --id=onboarding --format=json
php psb telegram:flow-map:validate --scope=branch
php psb telegram:flow-map:validate --scope=cjm --strict
```

Команды `telegram:flow-map*` живут в компоненте, но получают данные только через DI-контракты:

- `PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapRegistryResolverInterface`
- `PhpSoftBox\Telegram\Cli\FlowMap\TelegramFlowMapSettingsInterface`

Это позволяет держать чтение app-конфига в app-layer, а CLI-реализацию — в компоненте.
