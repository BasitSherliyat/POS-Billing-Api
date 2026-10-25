<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_are_shared_with_authenticated_staff_and_admins_can_update_them(): void
    {
        $this->getJson('/api/store-settings')->assertUnauthorized();

        $this->authenticateApiUser('staff');
        $this->getJson('/api/store-settings')
            ->assertOk()
            ->assertJsonPath('mart_name', 'Mart Billing')
            ->assertJsonPath('logo_path', null);
        $this->postJson('/api/store-settings', ['mart_name' => 'Unauthorized Store'])->assertForbidden();

        $this->authenticateApiUser('admin');
        $this->postJson('/api/store-settings', [
            'mart_name' => 'Green Valley Mart',
            'address' => 'Main Road, Lahore',
            'contact_number' => '03001234567',
        ])->assertOk()
            ->assertJsonPath('mart_name', 'Green Valley Mart')
            ->assertJsonPath('address', 'Main Road, Lahore')
            ->assertJsonPath('contact_number', '03001234567');

        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'mart_name' => 'Green Valley Mart',
            'address' => 'Main Road, Lahore',
            'contact_number' => '03001234567',
        ]);

        $this->authenticateApiUser('staff');
        $this->getJson('/api/store-settings')->assertJsonPath('mart_name', 'Green Valley Mart');
    }

    public function test_store_settings_accept_and_remove_a_logo(): void
    {
        $this->authenticateApiUser('admin');
        $logo = UploadedFile::fake()->createWithContent(
            'mart.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS2cAAAAASUVORK5CYII='),
        );

        $response = $this->post('/api/store-settings', [
            'mart_name' => 'Logo Mart',
            'address' => '',
            'contact_number' => '',
            'remove_logo' => '0',
            'logo' => $logo,
        ], ['Accept' => 'application/json'])->assertOk();

        $logoPath = $response->json('logo_path');
        $this->assertStringStartsWith('/images/mart/', $logoPath);
        $fullLogoPath = public_path(ltrim($logoPath, '/'));
        $this->assertFileExists($fullLogoPath);

        $this->postJson('/api/store-settings', [
            'mart_name' => 'Logo Mart',
            'remove_logo' => true,
        ])->assertOk()->assertJsonPath('logo_path', null);
        $this->assertFileDoesNotExist($fullLogoPath);
    }

    public function test_store_settings_validate_business_details_and_logo(): void
    {
        $this->authenticateApiUser('admin');

        $this->postJson('/api/store-settings', [])->assertUnprocessable()
            ->assertJsonValidationErrors('mart_name');
        $this->post('/api/store-settings', [
            'mart_name' => 'Invalid Logo',
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('logo');
    }

    public function test_authenticated_users_can_upload_item_images(): void
    {
        $this->authenticateApiUser();

        $response = $this->post('/api/items/upload-image', [
            'image' => UploadedFile::fake()->createWithContent(
                'rice.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS2cAAAAASUVORK5CYII='),
            ),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonStructure(['item_image']);
        $imagePath = public_path(ltrim($response->json('item_image'), '/'));
        $this->assertFileExists($imagePath);
        File::delete($imagePath);

        $this->postJson('/api/items/upload-image', [])->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_authenticated_users_can_bulk_upload_items_from_csv_with_row_errors(): void
    {
        $this->authenticateApiUser();
        $category = $this->postJson('/api/categories', [
            'category_name' => 'Grocery',
        ])->assertCreated();

        $csv = implode("\n", [
            'item_name,category_name,price,unit,barcode,item_image',
            'Rice,Grocery,250,kg,SKU-RICE,',
            'Invalid item,Unknown,50,each,,',
            'Flour,Grocery,,bag,,',
            'Duplicate barcode,Grocery,10,bag,SKU-RICE,',
        ]);

        $this->post('/api/items/bulk-upload', [
            'file' => UploadedFile::fake()->createWithContent('items.csv', $csv),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported_count', 2)
            ->assertJsonPath('failed_count', 2)
            ->assertJsonPath('items.0.item_name', 'Rice')
            ->assertJsonPath('errors.0.row', 3)
            ->assertJsonPath('errors.0.errors.category_name.0', 'The selected category name does not exist.')
            ->assertJsonPath('errors.1.row', 5)
            ->assertJsonPath('errors.1.errors.barcode.0', 'The barcode has already been used in this CSV file.');

        $this->assertDatabaseHas('items', [
            'item_name' => 'Rice',
            'category_id' => $category->json('id'),
            'price' => '250.00',
            'unit' => 'kg',
            'barcode' => 'SKU-RICE',
        ]);
        $this->assertDatabaseHas('items', [
            'item_name' => 'Flour',
            'category_id' => $category->json('id'),
            'price' => '0.00',
            'unit' => 'bag',
        ]);
        $this->assertDatabaseCount('items', 2);
    }

    public function test_bulk_item_upload_rejects_invalid_csv_headers_and_missing_file(): void
    {
        $this->authenticateApiUser();

        $this->postJson('/api/items/bulk-upload', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->post('/api/items/bulk-upload', [
            'file' => UploadedFile::fake()->createWithContent('items.csv', "name,category_name,unit\nRice,Grocery,kg"),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_staff_and_admin_api_permissions_match_their_roles(): void
    {
        $staff = $this->authenticateApiUser('staff');

        $this->getJson('/api/items')->assertOk();
        $this->getJson('/api/categories')->assertOk();
        $this->getJson('/api/customers')->assertOk();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/categories', ['category_name' => 'Restricted'])->assertForbidden();
        $this->postJson('/api/items', [
            'item_name' => 'Restricted',
            'category_id' => 1,
            'price' => 1,
            'unit' => 'piece',
        ])->assertForbidden();

        $customer = $this->postJson('/api/customers', [
            'customer_name' => 'Walk-in',
            'customer_contact' => '03000000000',
        ])->assertCreated();
        $this->deleteJson('/api/customers/'.$customer->json('id'))->assertForbidden();

        $invoice = $this->postJson('/api/sales-invoices', [
            'invoice_no' => 'STAFF-001',
            'user_id' => $staff->id,
            'customer_id' => $customer->json('id'),
            'invoice_date' => '2026-10-22',
            'grand_total' => 10,
        ])->assertCreated();
        $this->patchJson('/api/sales-invoices/'.$invoice->json('id'), [
            'payment_status' => 'paid',
            'paid_amount' => 10,
            'due_amount' => 0,
        ])->assertOk();

        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->withToken(auth('api')->login($admin));
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/items')->assertOk();
        $this->postJson('/api/categories', ['category_name' => 'Admin category'])->assertCreated();
    }

    public function test_categories_customers_and_items_support_crud(): void
    {
        $this->authenticateApiUser();

        $categoryResponse = $this->postJson('/api/categories', [
            'category_name' => 'Grocery',
        ]);
        $categoryResponse->assertCreated()->assertJsonPath('category_name', 'Grocery');
        $categoryId = $categoryResponse->json('id');

        $this->getJson('/api/categories')->assertOk()->assertJsonPath('data.0.id', $categoryId);
        $this->patchJson("/api/categories/{$categoryId}", [
            'category_name' => 'Food',
        ])->assertOk()->assertJsonPath('category_name', 'Food');

        $customerResponse = $this->postJson('/api/customers', [
            'customer_name' => 'Ali Ahmed',
            'customer_contact' => '03001234567',
        ]);
        $customerResponse->assertCreated();
        $customerId = $customerResponse->json('id');
        $this->getJson("/api/customers/{$customerId}")->assertOk();
        $this->patchJson("/api/customers/{$customerId}", [
            'shop_name' => 'ABC Store',
        ])->assertOk()->assertJsonPath('shop_name', 'ABC Store');

        $itemResponse = $this->postJson('/api/items', [
            'item_name' => 'Rice',
            'category_id' => $categoryId,
            'price' => 250,
            'unit' => 'kg',
            'barcode' => 'SKU-RICE',
        ]);
        $itemResponse->assertCreated()
            ->assertJsonPath('category.id', $categoryId)
            ->assertJsonPath('price', '250.00')
            ->assertJsonPath('barcode', 'SKU-RICE');
        $itemId = $itemResponse->json('id');
        $this->postJson('/api/items', [
            'item_name' => 'Duplicate barcode',
            'category_id' => $categoryId,
            'unit' => 'piece',
            'barcode' => 'SKU-RICE',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->patchJson("/api/items/{$itemId}", [
            'barcode' => 'SKU-RICE-UPDATED',
        ])->assertOk()->assertJsonPath('barcode', 'SKU-RICE-UPDATED');
        $this->assertDatabaseHas('items', [
            'id' => $itemId,
            'barcode' => 'SKU-RICE-UPDATED',
        ]);

        $this->deleteJson("/api/categories/{$categoryId}")->assertStatus(409);
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.items_count', 1);
        $this->getJson("/api/items/{$itemId}")->assertOk();
        $this->deleteJson("/api/items/{$itemId}")->assertNoContent();
        $this->deleteJson("/api/categories/{$categoryId}")->assertNoContent();
        $this->deleteJson("/api/customers/{$customerId}")->assertNoContent();
    }

    public function test_invoices_and_invoice_items_support_crud_and_relationships(): void
    {
        $this->authenticateApiUser();

        $category = $this->postJson('/api/categories', ['category_name' => 'Grocery'])->assertCreated();
        $customer = $this->postJson('/api/customers', [
            'customer_name' => 'Ali Ahmed',
            'customer_contact' => '03001234567',
        ])->assertCreated();
        $user = $this->postJson('/api/users', [
            'user_name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
            'user_type' => 'admin',
        ])->assertCreated();
        $item = $this->postJson('/api/items', [
            'item_name' => 'Rice',
            'category_id' => $category->json('id'),
            'unit' => 'kg',
        ])->assertCreated();

        $invoice = $this->postJson('/api/sales-invoices', [
            'invoice_no' => 'INV-0001',
            'customer_id' => $customer->json('id'),
            'user_id' => $user->json('id'),
            'invoice_date' => '2026-10-21',
            'grand_total' => 2500,
            'payment_status' => 'partial',
        ])->assertCreated()->assertJsonPath('items', []);
        $invoiceId = $invoice->json('id');

        $line = $this->postJson('/api/sales-invoice-items', [
            'invoice_id' => $invoiceId,
            'item_id' => $item->json('id'),
            'item_name' => 'Rice',
            'unit' => 'kg',
            'quantity' => 10,
            'price' => 250,
            'total' => 2500,
        ])->assertCreated()->assertJsonPath('item.item_name', 'Rice');
        $lineId = $line->json('id');

        $this->getJson("/api/sales-invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('items.0.id', $lineId)
            ->assertJsonPath('customer.id', $customer->json('id'));
        $this->getJson("/api/sales-invoice-items/{$lineId}")->assertOk();
        $this->patchJson("/api/sales-invoice-items/{$lineId}", [
            'quantity' => 5,
        ])->assertOk()->assertJsonPath('quantity', '5.000');
        $this->patchJson("/api/sales-invoices/{$invoiceId}", [
            'payment_status' => 'paid',
        ])->assertOk()->assertJsonPath('payment_status', 'paid');
        $this->deleteJson("/api/sales-invoices/{$invoiceId}")->assertNoContent();
        $this->assertDatabaseMissing('sales_invoice_items', ['id' => $lineId]);
    }

    public function test_users_hash_passwords_validate_unique_email_and_never_return_passwords(): void
    {
        $this->authenticateApiUser();

        $response = $this->postJson('/api/users', [
            'user_name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
            'user_type' => 'super admin',
        ]);
        $response->assertCreated()->assertJsonMissingPath('password')->assertJsonPath('user_type', 'super admin');

        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secure-password', $user->password));

        $this->postJson('/api/users', [
            'user_name' => 'duplicate',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
            'user_type' => 'staff',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->patchJson("/api/users/{$user->id}", [
            'user_name' => 'manager',
            'user_type' => 'admin',
        ])->assertOk()->assertJsonMissingPath('password')
            ->assertJsonPath('user_name', 'manager')
            ->assertJsonPath('user_type', 'admin');
        $this->getJson("/api/users/{$user->id}")->assertOk()->assertJsonMissingPath('password');

        $this->postJson('/api/users', [
            'user_name' => 'invalid',
            'email' => 'invalid-type@example.com',
            'password' => 'secure-password',
            'user_type' => 'owner',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_type');

        $this->postJson('/api/users', [
            'user_name' => 'missing-type',
            'email' => 'missing-type@example.com',
            'password' => 'secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_type');

        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();
    }

    public function test_jwt_login_me_logout_and_legacy_password_upgrade(): void
    {
        config(['jwt.secret' => 'test-only-jwt-secret-that-is-long-enough']);

        DB::table('users')->insert([
            'user_name' => 'admin',
            'email' => 'legacy@example.com',
            'password' => 'admin123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/categories')->assertUnauthorized();
        $this->postJson('/api/auth/login', [
            'email' => 'legacy@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'legacy@example.com',
            'password' => 'admin123',
        ])->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.email', 'legacy@example.com')
            ->assertJsonMissingPath('user.password');
        $token = $login->json('access_token');

        $this->assertTrue(Hash::check('admin123', User::where('email', 'legacy@example.com')->value('password')));

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'legacy@example.com')
            ->assertJsonMissingPath('password');

        $refreshedToken = $this->withToken($token)->postJson('/api/auth/refresh')
            ->assertOk()
            ->json('access_token');
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($refreshedToken)->getJson('/api/auth/me')->assertOk();
        $this->withToken($refreshedToken)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($refreshedToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_jwt_access_token_stays_valid_until_logout(): void
    {
        $this->assertNull(config('jwt.ttl'));
        config([
            'jwt.secret' => 'test-only-jwt-secret-that-is-long-enough',
        ]);

        $user = User::factory()->create(['user_type' => 'admin']);
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('expires_in', null);
        $token = $login->json('access_token');

        $payload = app('tymon.jwt')->setToken($token)->getPayload();
        $this->assertFalse($payload->hasKey('exp'));

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_signup_creates_hashed_user_and_returns_a_valid_jwt(): void
    {
        config(['jwt.secret' => 'test-only-jwt-secret-that-is-long-enough']);

        $response = $this->postJson('/api/auth/signup', [
            'user_name' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.user_name', 'new-user')
            ->assertJsonPath('user.email', 'new-user@example.com')
            ->assertJsonPath('user.user_type', 'staff')
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secure-password', $user->password));

        $this->withToken($response->json('access_token'))->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->postJson('/api/auth/signup', [
            'user_name' => 'another-user',
            'email' => 'new-user@example.com',
            'password' => 'secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/signup', [
            'user_name' => 'elevated-user',
            'email' => 'elevated-user@example.com',
            'password' => 'secure-password',
            'user_type' => 'super admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_type');
    }

    private function authenticateApiUser(string $userType = 'super admin'): User
    {
        config(['jwt.secret' => 'test-only-jwt-secret-that-is-long-enough']);
        $user = User::factory()->create(['user_type' => $userType]);

        $this->withToken(auth('api')->login($user));

        return $user;
    }
}
