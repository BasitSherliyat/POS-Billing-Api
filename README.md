# Mart Billing API

Laravel REST API for the `mart` database. All endpoints are JSON and are prefixed with `/api`.

## Setup

For a fresh install, create `.env` from `.env.example` if it does not exist, configure MariaDB/MySQL and create the database, then run:

```sh
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

For an existing database created from the provided SQL dump, configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`, then run `php artisan jwt:secret`. The dump already creates the application tables, so do not run the create-table migrations against that database.

## Endpoints

Each resource supports `GET /api/{resource}` (paginated, 15 per page), `POST /api/{resource}`, `GET /api/{resource}/{id}`, `PUT/PATCH /api/{resource}/{id}`, and `DELETE /api/{resource}/{id}`.

All CRUD endpoints require a JWT bearer token. Authentication endpoints:

| Method | API path | Purpose |
| --- | --- | --- |
| `POST` | `/api/auth/signup` | Register with `user_name`, `email`, and `password`; returns an access token |
| `POST` | `/api/auth/login` | Authenticate with `email` and `password`; returns an access token |
| `GET` | `/api/auth/me` | Return the authenticated user |
| `POST` | `/api/auth/refresh` | Revoke the current token and return a replacement |
| `POST` | `/api/auth/logout` | Revoke the current token |

Send authenticated requests with `Authorization: Bearer {access_token}`. JWTs expire after 60 minutes by default; refresh tokens within the configured refresh window. Login is rate-limited. Generate a private signing key before use by running `php artisan jwt:secret` and keep the resulting `JWT_SECRET` confidential; never use a committed or shared signing secret.

Example login request:

```json
{
  "email": "admin@mail.com",
  "password": "admin123"
}
```

Signup and login responses include `access_token`; pass that value as the bearer token on later CRUD calls. Change the sample password after the first login.

The SQL dump stores its sample `admin` password as plaintext. For compatibility, the first successful login upgrades a legacy plaintext password to Laravel's configured password hash. New passwords are always hashed.

| Resource | API path | Database table |
| --- | --- | --- |
| Categories | `/api/categories` | `categories` |
| Customers | `/api/customers` | `customers` |
| Items | `/api/items` | `items` |
| Sales invoices | `/api/sales-invoices` | `sales_invoices` |
| Sales invoice items | `/api/sales-invoice-items` | `sales_invoice_items` |
| Users | `/api/users` | `users` |

The API returns `201` when creating a record, `200` when reading or updating, `204` when deleting, `404` for unknown records, and Laravel validation errors (`422`) for invalid input. Lists return Laravel pagination JSON. Items include their category; invoices include their customer, user, and line items. Sales invoice items can be managed directly through their resource endpoint.

### Bulk item CSV upload

Send an authenticated `multipart/form-data` request to `POST /api/items/bulk-upload` with the CSV file in the `file` field. The CSV must include `item_name`, `category_name`, and `unit` columns. Optional columns are `price` and `item_image`; an empty price uses the database default. `category_name` must exactly match one existing category name. Unknown or duplicate category names are reported as row errors.

For example:

```csv
item_name,category_name,price,unit,item_image
Rice,Grocery,250,kg,
Flour,Grocery,,bag,
```

Valid rows are imported even when other rows fail validation. The `200` response includes `imported_count`, `failed_count`, created item IDs and names in `items`, and per-row validation details in `errors`. Row numbers count the header as row 1. The upload is limited to 10 MB.

## Request fields

- **Category:** `category_name` (required, max 100).
- **Customer:** `customer_name` and `customer_contact` (required); `shop_name`, `alt_number`, `address`, and `image` are optional.
- **Item:** `item_name`, `category_id`, and `unit` (required); `price` (non-negative, defaults to 0) and `item_image` are optional.
- **Sales invoice:** `invoice_no`, `user_id`, and `invoice_date` (required); `customer_id` is nullable. `subtotal`, `discount`, `tax`, `grand_total`, `paid_amount`, and `due_amount` are non-negative and default to 0; `payment_status` is `unpaid`, `partial`, or `paid`; `notes` is optional.
- **Sales invoice item:** `invoice_id`, `item_name`, `unit`, `quantity`, `price`, and `total` (required); `item_id` is nullable; `quantity` must be greater than 0; `discount` is non-negative and defaults to 0.
- **User:** `user_name`, `email`, `password`, and `user_type` (required on create); `user_type` must be `admin`, `staff`, or `super admin`. Passwords must be at least 8 characters and are stored hashed. Passwords are never included in API responses. On update, send `password` only when changing it; `user_type` is optional.

Foreign-key validation is applied to item categories, invoice customers/users, and invoice-item invoices/items. Deleting a customer detaches it from invoices; deleting an invoice removes its line items; deleting an item detaches it from historical invoice items. Categories with items and users with invoices cannot be deleted and return `409 Conflict`.

JWT authentication protects the CRUD routes. Public signup always assigns the `staff` type and does not accept `user_type`. Super admins can access all resources. Admins can access all resources except user management. Staff can access billing resources and customer records, but cannot manage categories, items, or users, and cannot delete customers. The API enforces these permissions in addition to the frontend route restrictions.
