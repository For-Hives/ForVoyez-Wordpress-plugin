# API Documentation: Tokens Endpoint

## Overview

This document describes the `/api/tokens` endpoint that allows clients to retrieve information about a user's remaining tokens (credits), user details, and subscription status.

## Endpoint Information

- **URL**: `/api/tokens`
- **Method**: GET
- **Description**: Retrieves user information including remaining credits and subscription status

## Authentication

The endpoint requires authentication using a JWT token.

- **Authentication Type**: Bearer Token
- **Header**: `Authorization: Bearer YOUR_JWT_TOKEN`

## Request

### Headers

| Header          | Value                   | Description                   |
| --------------- | ----------------------- | ----------------------------- |
| `Authorization` | `Bearer YOUR_JWT_TOKEN` | JWT token issued for the user |

### Parameters

No parameters are required for this endpoint.

## Response

### Success Response (200 OK)

```json
{
	"success": true,
	"user": {
		"id": "user_clerk_id",
		"credits": 100,
		"registeredAt": "2023-01-15T10:30:00.000Z",
		"name": "User Name",
		"email": "user@example.com"
	},
	"subscription": {
		"isSubscribed": true,
		"status": "active",
		"statusFormatted": "Active",
		"plan": {
			"name": "Premium Plan",
			"description": "Full access to all features"
		},
		"renewsAt": "2023-12-15T00:00:00.000Z",
		"endsAt": null
	},
	"token": {
		"name": "WordPress API Token",
		"createdAt": "2023-06-10T15:45:00.000Z",
		"expiredAt": "2024-06-10T15:45:00.000Z"
	}
}
```

#### Response Fields Explained

**User Information:**

- `id`: Clerk ID of the user
- `credits`: Remaining token credits for the user
- `registeredAt`: Date when the user registered
- `name`: User's name (if available)
- `email`: User's email address (if available)

**Subscription Information:**

- `isSubscribed`: Boolean indicating if the user has an active subscription
- `status`: Current status of the subscription (e.g., "active")
- `statusFormatted`: Human-readable status
- `plan`: Information about the subscribed plan
  - `name`: Name of the plan
  - `description`: Description of what the plan includes
- `renewsAt`: Date when the subscription will renew
- `endsAt`: Date when the subscription will end (if applicable)

**Token Information:**

- `name`: Name of the API token
- `createdAt`: Date when the token was created
- `expiredAt`: Date when the token will expire

### Error Responses

| Status Code | Description           | Response Body                                                                                                                                                    |
| ----------- | --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 400         | Bad Request           | `{ "error": "Malformed token: missing userId" }`                                                                                                                 |
| 401         | Unauthorized          | `{ "error": "Missing or invalid authentication token" }` or `{ "error": "Invalid or expired token" }` or `{ "error": "Token not found or expired in database" }` |
| 404         | Not Found             | `{ "error": "User not found" }`                                                                                                                                  |
| 500         | Internal Server Error | `{ "error": "Server error", "details": "Error message" }`                                                                                                        |

## Example Usage

### Example Request (cURL)

```bash
curl -X GET https://yourdomain.com/api/tokens \
     -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example Request (WordPress)

```php
$api_url = 'https://yourdomain.com/api/tokens';
$jwt_token = 'YOUR_JWT_TOKEN'; // Token issued for the user

$response = wp_remote_get($api_url, [
	'headers' => [
		'Authorization' => 'Bearer ' . $jwt_token,
	],
	'timeout' => 15,
]);

if (!is_wp_error($response)) {
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if (isset($data['success']) && $data['success']) {
		// Access user data
		$credits = $data['user']['credits'];
		$isSubscribed = $data['subscription']['isSubscribed'];

		// Do something with the data
		echo "You have {$credits} credits remaining.";

		if ($isSubscribed) {
			echo 'Your subscription plan: ' . $data['subscription']['plan']['name'];
		}
	} else {
		echo 'Error: ' . ($data['error'] ?? 'Unknown error');
	}
}
```

## Notes

- The token must be valid and not expired
- The token must exist in the database
- The user associated with the token must exist
