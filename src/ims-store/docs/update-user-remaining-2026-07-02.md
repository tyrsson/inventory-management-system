# Update User via ims-store — Remaining Work (2026-07-02)

- Split the PATCH `update` route's terminal handler off from `UserListHandler` (GET admin list) so ims-store can override the update path without hijacking the list route.
- Complete the RequestHandler override in `ims-store` (currently a stub `Http/Handler/Admin/UpdateUserHandler.php` + empty `Http/Container/UpdateUserHandlerFactory.php`) and wire it into the new dedicated route/DI key.
- Fix the mismatched `command_map` entry in `Ims\Store\ConfigProvider::getCommandMap()` — keyed to `SaveUserCommand::class`, should key to `Webware\UserManager\Command\UpdateUserCommand::class` pointing at the ims-store `CommandHandler`.
- Reconcile `Ims\Store\CommandHandler\UpdateUserCommandHandler`'s type guard — it currently expects `Ims\Store\Entity\User` as the command; confirm whether entity-as-command is still the intended design given `CommandHandlerResolver` resolves by `$command::class`.
- Fix `Ims\Store\Entity\User::withStoreId()` — replaces the whole `details` array instead of merging (data-loss bug, inconsistent with `withDetail()`).
- Fix `Entity\User::toArray()` risk for the store-aware subclass — `(array) $this` will include the hooked `storeId` property as its own array key, which has no corresponding DB column (only `details` JSON exists).
- Register the ims-store `Repository` override (currently empty) if the base `UserRepository::save()`/`update()` isn't sufficient once `toArray()` is fixed.
- Register all new factories/handlers in `Ims\Store\ConfigProvider::getDependencies()` — only the `Entity\User` override is registered today.
