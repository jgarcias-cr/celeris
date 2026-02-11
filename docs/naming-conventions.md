# Naming Conventions

- Namespaces: `Vendor\Package\Module\...` mapped via PSR-4 to `src/`.
- Classes: PascalCase (e.g., `RequestContext`, `UserRepository`).
- Interfaces: PascalCase with `Interface` suffix (e.g., `CacheInterface`).
- Traits: PascalCase with `Trait` suffix.
- Services: use `XxxService` when a service encapsulates business functionality; factories use `XxxFactory`.
- Exceptions: end with `Exception` and extend `\Throwable` implementations.
- Middleware: `SomethingMiddleware` implementing `MiddlewareInterface`.
- Event DTOs: `SomethingOccurred` or `SomethingEvent` depending on intent; keep immutable.
- Config keys: kebab-case or snake_case in files; map to typed config objects in code.

General rules
  - Prefer explicit method names and small interfaces.
  - Keep functions and classes focused; single responsibility.
  - Use strict typing (`declare(strict_types=1);`) in all files.
