# API Testing Guide

This project uses **API Platform 4** and **Zenstruck Foundry** for testing. We follow a functional testing strategy that verifies the API endpoints behave as expected.

## Frameworks & Tools

-   **PHPUnit**: The underlying testing framework.
-   **API Platform Test Utils**: Provides `ApiTestCase` and `Client` for making HTTP requests to the kernel.
-   **Zenstruck Foundry**: For creating creating expressive, object-oriented data fixtures.

## Best Practices

1.  **Use `ApiTestCase`**: All API tests should extend `ApiPlatform\Symfony\Bundle\Test\ApiTestCase`.
2.  **Use Factories**: Always use Foundry factories to create data. Do not instantiate entities manually or use raw SQL/DQL unless absolutely necessary.
3.  **Reset Database**: Use `Zenstruck\Foundry\Test\ResetDatabase` trait to ensure a clean state between tests.
4.  **Test Operations**: Cover standard CRUD operations (GET collection, POST, GET item, PATCH, DELETE) for each resource.
5.  **Test Security**: Verify that access controls (Roles) are working.

## Creating a New Test

Create a new test file in `api/tests/Api/` naming it `<EntityName>Test.php`.

### Example Template

```php
<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Project;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProjectTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        // 1. Arrange: Create data
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ProjectFactory::createMany(3, ['account' => $user->getAccount()]);

        // 2. Act: Make request
        $client = static::createClient();
        $client->loginUser($user->_real());
        $response = $client->request('GET', '/projects');

        // 3. Assert: Verify response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);
        $this->assertMatchesResourceCollectionJsonSchema(Project::class);
    }
}
```

## Running Tests

Run all tests:

```bash
php bin/phpunit
```

Run a specific test file:

```bash
php bin/phpunit tests/Api/ProjectTest.php
```
