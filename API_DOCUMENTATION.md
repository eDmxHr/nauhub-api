# Nauhub API Documentation

**Base URL:** `http://127.0.0.1:8000/api`

**Version:** 1.0

---

## Table of Contents

1. [Authentication](#authentication)
2. [Profiles](#profiles)
3. [Categories](#categories)
4. [Genres](#genres)
5. [Videos](#videos)
6. [Error Responses](#error-responses)

---

## Authentication

### 1. Send OTP

Send OTP to phone number for authentication.

**Endpoint:** `POST /auth/send-otp`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "phone": "9999999"
}
```

**Success Response (200):**
```json
{
  "message": "OTP sent successfully",
  "otp": "123456",
  "expires_in": "5 minutes"
}
```

**Validation Errors (422):**
```json
{
  "message": "The phone field is required.",
  "errors": {
    "phone": [
      "The phone field is required."
    ]
  }
}
```

---

### 2. Verify OTP

Verify OTP and login/register user.

**Endpoint:** `POST /auth/verify-otp`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "phone": "9999999",
  "otp": "123456",
  "name": "John Doe"
}
```

**Parameters:**
- `phone` (required, string, 7 digits) - Phone number
- `otp` (required, string, 6 digits) - OTP code
- `name` (optional, string) - User name (only for new users)

**Success Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": null,
    "phone": "9999999",
    "phone_verified": true,
    "role": "user",
    "is_subscribed": false,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  },
  "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz"
}
```

**Error Responses:**

Invalid OTP (401):
```json
{
  "message": "Invalid or expired OTP"
}
```

---

### 3. Get Authenticated User

Get currently authenticated user details.

**Endpoint:** `GET /auth/user`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": null,
    "phone": "9999999",
    "phone_verified": true,
    "role": "user",
    "is_subscribed": false,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

---

### 4. Logout

Logout and revoke current access token.

**Endpoint:** `POST /auth/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

## Profiles

### 1. Get All Profiles

Get all profiles for authenticated user.

**Endpoint:** `GET /profiles`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Profiles retrieved successfully",
  "profiles": [
    {
      "id": 1,
      "user_id": 1,
      "name": "John",
      "avatar": "https://example.com/avatar1.jpg",
      "is_kids_profile": false,
      "created_at": "2025-12-28T10:00:00.000000Z",
      "updated_at": "2025-12-28T10:00:00.000000Z"
    },
    {
      "id": 2,
      "user_id": 1,
      "name": "Kids",
      "avatar": "https://example.com/kids-avatar.jpg",
      "is_kids_profile": true,
      "created_at": "2025-12-28T10:05:00.000000Z",
      "updated_at": "2025-12-28T10:05:00.000000Z"
    }
  ]
}
```

---

### 2. Create Profile

Create a new profile (max 5 profiles per user).

**Endpoint:** `POST /profiles`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John",
  "avatar": "https://example.com/avatar.jpg",
  "pin": "1234",
  "is_kids_profile": false
}
```

**Parameters:**
- `name` (required, string, max 255) - Profile name
- `avatar` (optional, string) - Avatar URL
- `pin` (optional, string, 4 digits) - 4-digit PIN for profile protection
- `is_kids_profile` (optional, boolean) - Kids profile flag

**Success Response (201):**
```json
{
  "message": "Profile created successfully",
  "profile": {
    "id": 3,
    "user_id": 1,
    "name": "John",
    "avatar": "https://example.com/avatar.jpg",
    "is_kids_profile": false,
    "created_at": "2025-12-28T11:00:00.000000Z",
    "updated_at": "2025-12-28T11:00:00.000000Z"
  }
}
```

**Error Response (400):**
```json
{
  "message": "Maximum number of profiles (5) reached"
}
```

---

### 3. Get Single Profile

Get a specific profile.

**Endpoint:** `GET /profiles/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Profile retrieved successfully",
  "profile": {
    "id": 1,
    "user_id": 1,
    "name": "John",
    "avatar": "https://example.com/avatar.jpg",
    "is_kids_profile": false,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "message": "Profile not found"
}
```

---

### 4. Update Profile

Update an existing profile.

**Endpoint:** `PUT /profiles/{id}`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Updated",
  "avatar": "https://example.com/new-avatar.jpg",
  "pin": "5678",
  "is_kids_profile": false
}
```

**Success Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "user_id": 1,
    "name": "John Updated",
    "avatar": "https://example.com/new-avatar.jpg",
    "is_kids_profile": false,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T12:00:00.000000Z"
  }
}
```

---

### 5. Delete Profile

Delete a profile (cannot delete last profile).

**Endpoint:** `DELETE /profiles/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Profile deleted successfully"
}
```

**Error Response (400):**
```json
{
  "message": "Cannot delete the last profile"
}
```

---

### 6. Verify Profile PIN

Verify PIN before accessing a PIN-protected profile.

**Endpoint:** `POST /profiles/{id}/verify-pin`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "pin": "1234"
}
```

**Success Response (200):**
```json
{
  "message": "PIN verified",
  "verified": true
}
```

**Error Response (401):**
```json
{
  "message": "Invalid PIN",
  "verified": false
}
```

**No PIN Required (200):**
```json
{
  "verified": true
}
```

---

## Categories

### 1. Get All Categories (Public)

Get all categories with video count.

**Endpoint:** `GET /categories`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Categories retrieved successfully",
  "categories": [
    {
      "id": 1,
      "name": "Action",
      "slug": "action",
      "description": "Action-packed movies and series",
      "sort_order": 1,
      "is_active": true,
      "videos_count": 15,
      "created_at": "2025-12-28T10:00:00.000000Z",
      "updated_at": "2025-12-28T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "Drama",
      "slug": "drama",
      "description": "Dramatic content",
      "sort_order": 2,
      "is_active": true,
      "videos_count": 8,
      "created_at": "2025-12-28T10:05:00.000000Z",
      "updated_at": "2025-12-28T10:05:00.000000Z"
    }
  ]
}
```

---

### 2. Get Single Category (Public)

Get a specific category with video count.

**Endpoint:** `GET /categories/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Category retrieved successfully",
  "category": {
    "id": 1,
    "name": "Action",
    "slug": "action",
    "description": "Action-packed movies and series",
    "sort_order": 1,
    "is_active": true,
    "videos_count": 15,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "message": "Category not found"
}
```

---

### 3. Create Category (Admin Only)

Create a new category.

**Endpoint:** `POST /admin/categories`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Action",
  "description": "Action-packed movies and series",
  "sort_order": 1,
  "is_active": true
}
```

**Parameters:**
- `name` (required, string, max 255, unique) - Category name
- `description` (optional, string) - Category description
- `sort_order` (optional, integer, min 0) - Display order (default: 0)
- `is_active` (optional, boolean) - Active status (default: true)

**Success Response (201):**
```json
{
  "message": "Category created successfully",
  "category": {
    "id": 1,
    "name": "Action",
    "slug": "action",
    "description": "Action-packed movies and series",
    "sort_order": 1,
    "is_active": true,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

**Authorization Error (403):**
```json
{
  "message": "Unauthorized. Admin access required."
}
```

---

### 4. Update Category (Admin Only)

Update an existing category.

**Endpoint:** `PUT /admin/categories/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Action & Adventure",
  "description": "Updated description",
  "sort_order": 5,
  "is_active": false
}
```

**Success Response (200):**
```json
{
  "message": "Category updated successfully",
  "category": {
    "id": 1,
    "name": "Action & Adventure",
    "slug": "action-adventure",
    "description": "Updated description",
    "sort_order": 5,
    "is_active": false,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T12:00:00.000000Z"
  }
}
```

---

### 5. Delete Category (Admin Only)

Delete a category (only if no videos associated).

**Endpoint:** `DELETE /admin/categories/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Success Response (200):**
```json
{
  "message": "Category deleted successfully"
}
```

**Error Response (400):**
```json
{
  "message": "Cannot delete category with associated videos. Remove videos first."
}
```

---

## Genres

### 1. Get All Genres (Public)

Get all genres with video count.

**Endpoint:** `GET /genres`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Genres retrieved successfully",
  "genres": [
    {
      "id": 1,
      "name": "Sci-Fi",
      "slug": "sci-fi",
      "description": "Science fiction content",
      "icon": "🚀",
      "color": "#0080FF",
      "is_active": true,
      "videos_count": 12,
      "created_at": "2025-12-28T10:00:00.000000Z",
      "updated_at": "2025-12-28T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "Romance",
      "slug": "romance",
      "description": "Romantic content",
      "icon": "💕",
      "color": "#FF69B4",
      "is_active": true,
      "videos_count": 7,
      "created_at": "2025-12-28T10:05:00.000000Z",
      "updated_at": "2025-12-28T10:05:00.000000Z"
    }
  ]
}
```

---

### 2. Get Single Genre (Public)

Get a specific genre with video count.

**Endpoint:** `GET /genres/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Genre retrieved successfully",
  "genre": {
    "id": 1,
    "name": "Sci-Fi",
    "slug": "sci-fi",
    "description": "Science fiction content",
    "icon": "🚀",
    "color": "#0080FF",
    "is_active": true,
    "videos_count": 12,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

---

### 3. Create Genre (Admin Only)

Create a new genre.

**Endpoint:** `POST /admin/genres`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Sci-Fi",
  "description": "Science fiction content",
  "icon": "🚀",
  "color": "#0080FF",
  "is_active": true
}
```

**Parameters:**
- `name` (required, string, max 255, unique) - Genre name
- `description` (optional, string) - Genre description
- `icon` (optional, string, max 255) - Genre icon (emoji or URL)
- `color` (optional, string, hex color format #RRGGBB) - Genre color
- `is_active` (optional, boolean) - Active status (default: true)

**Success Response (201):**
```json
{
  "message": "Genre created successfully",
  "genre": {
    "id": 1,
    "name": "Sci-Fi",
    "slug": "sci-fi",
    "description": "Science fiction content",
    "icon": "🚀",
    "color": "#0080FF",
    "is_active": true,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z"
  }
}
```

**Validation Error (422):**
```json
{
  "message": "The color field must be a valid hex color.",
  "errors": {
    "color": [
      "The color field must be a valid hex color (e.g., #FF0000)."
    ]
  }
}
```

---

### 4. Update Genre (Admin Only)

Update an existing genre.

**Endpoint:** `PUT /admin/genres/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Science Fiction",
  "color": "#0099FF"
}
```

**Success Response (200):**
```json
{
  "message": "Genre updated successfully",
  "genre": {
    "id": 1,
    "name": "Science Fiction",
    "slug": "science-fiction",
    "description": "Science fiction content",
    "icon": "🚀",
    "color": "#0099FF",
    "is_active": true,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T12:00:00.000000Z"
  }
}
```

---

### 5. Delete Genre (Admin Only)

Delete a genre (only if no videos associated).

**Endpoint:** `DELETE /admin/genres/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Success Response (200):**
```json
{
  "message": "Genre deleted successfully"
}
```

**Error Response (400):**
```json
{
  "message": "Cannot delete genre with associated videos. Remove videos first."
}
```

---

## Videos

### 1. Get All Videos (Public - Paginated)

Get paginated list of videos with filters.

**Endpoint:** `GET /videos`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (optional, integer, default: 15) - Items per page
- `type` (optional, string: movie|series|anime) - Filter by video type
- `category_id` (optional, integer) - Filter by category
- `genre_id` (optional, integer) - Filter by genre
- `featured` (optional, boolean) - Filter featured videos
- `trending` (optional, boolean) - Filter trending videos
- `published` (optional, boolean, default: true) - Filter published videos

**Example Request:**
```
GET /videos?per_page=10&type=movie&featured=1&category_id=1
```

**Success Response (200):**
```json
{
  "message": "Videos retrieved successfully",
  "videos": [
    {
      "id": 1,
      "type": "movie",
      "title": "The Matrix",
      "description": "A computer hacker learns about the true nature of reality.",
      "thumbnail": "https://example.com/matrix.jpg",
      "banner": "https://example.com/matrix-banner.jpg",
      "cloudflare_video_uid": "placeholder_abc123",
      "duration": 8160,
      "release_year": 1999,
      "age_rating": "R",
      "imdb_rating": 8.7,
      "is_published": true,
      "is_featured": true,
      "is_trending": false,
      "view_count": 1520,
      "created_at": "2025-12-28T10:00:00.000000Z",
      "updated_at": "2025-12-28T10:00:00.000000Z",
      "categories": [
        {
          "id": 1,
          "name": "Action",
          "slug": "action"
        }
      ],
      "genres": [
        {
          "id": 1,
          "name": "Sci-Fi",
          "slug": "sci-fi"
        }
      ],
      "seasons": []
    }
  ],
  "pagination": {
    "total": 45,
    "per_page": 10,
    "current_page": 1,
    "last_page": 5,
    "from": 1,
    "to": 10
  }
}
```

---

### 2. Get Featured Videos (Public)

Get featured videos (max 10).

**Endpoint:** `GET /videos/featured`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Featured videos retrieved successfully",
  "videos": [
    {
      "id": 1,
      "type": "movie",
      "title": "The Matrix",
      "description": "A computer hacker learns about the true nature of reality.",
      "thumbnail": "https://example.com/matrix.jpg",
      "banner": "https://example.com/matrix-banner.jpg",
      "cloudflare_video_uid": "placeholder_abc123",
      "duration": 8160,
      "release_year": 1999,
      "age_rating": "R",
      "imdb_rating": 8.7,
      "is_published": true,
      "is_featured": true,
      "is_trending": false,
      "view_count": 1520,
      "created_at": "2025-12-28T10:00:00.000000Z",
      "updated_at": "2025-12-28T10:00:00.000000Z",
      "categories": [...],
      "genres": [...]
    }
  ]
}
```

---

### 3. Get Trending Videos (Public)

Get trending videos sorted by view count (max 10).

**Endpoint:** `GET /videos/trending`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Trending videos retrieved successfully",
  "videos": [...]
}
```

---

### 4. Get Single Video (Public)

Get a specific video with all details including seasons/episodes.

**Endpoint:** `GET /videos/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Video retrieved successfully",
  "video": {
    "id": 2,
    "type": "series",
    "title": "Breaking Bad",
    "description": "A chemistry teacher diagnosed with cancer turns to cooking meth.",
    "thumbnail": "https://example.com/bb-thumb.jpg",
    "banner": "https://example.com/bb-banner.jpg",
    "cloudflare_video_uid": null,
    "duration": null,
    "release_year": 2008,
    "age_rating": "TV-MA",
    "imdb_rating": 9.5,
    "is_published": true,
    "is_featured": true,
    "is_trending": true,
    "view_count": 3450,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z",
    "categories": [
      {
        "id": 2,
        "name": "Drama",
        "slug": "drama"
      }
    ],
    "genres": [
      {
        "id": 3,
        "name": "Crime",
        "slug": "crime"
      }
    ],
    "seasons": [
      {
        "id": 1,
        "video_id": 2,
        "season_number": 1,
        "title": "Season 1",
        "description": "Walt begins his transformation",
        "episodes": [
          {
            "id": 1,
            "season_id": 1,
            "episode_number": 1,
            "title": "Pilot",
            "description": "Walt's life changes forever",
            "cloudflare_video_uid": "episode_uid_123",
            "duration": 3480
          }
        ]
      }
    ]
  }
}
```

---

### 5. Increment View Count (Public)

Increment view count for a video.

**Endpoint:** `POST /videos/{id}/view`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "View count updated",
  "view_count": 1521
}
```

---

### 6. Create Video (Admin Only)

Create a new video.

**Endpoint:** `POST /admin/videos`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "type": "movie",
  "title": "The Matrix",
  "description": "A computer hacker learns about the true nature of reality.",
  "thumbnail": "https://example.com/matrix.jpg",
  "banner": "https://example.com/matrix-banner.jpg",
  "cloudflare_video_uid": "abc123def456",
  "duration": 8160,
  "release_year": 1999,
  "age_rating": "R",
  "imdb_rating": 8.7,
  "is_published": true,
  "is_featured": true,
  "is_trending": false,
  "category_ids": [1, 2],
  "genre_ids": [1, 3]
}
```

**Parameters:**
- `type` (required, string: movie|series|anime) - Video type
- `title` (required, string, max 255) - Video title
- `description` (optional, string) - Video description
- `thumbnail` (optional, string, max 500) - Thumbnail URL
- `banner` (optional, string, max 500) - Banner URL
- `cloudflare_video_uid` (optional, string, max 255) - Cloudflare Stream video UID (required for movies)
- `duration` (optional, integer, min 0) - Duration in seconds
- `release_year` (optional, integer, 1900-2030) - Release year
- `age_rating` (optional, string, max 10) - Age rating (e.g., PG-13, R, TV-MA)
- `imdb_rating` (optional, numeric, 0-10) - IMDB rating
- `is_published` (optional, boolean, default: false) - Published status
- `is_featured` (optional, boolean, default: false) - Featured status
- `is_trending` (optional, boolean, default: false) - Trending status
- `category_ids` (optional, array) - Array of category IDs
- `genre_ids` (optional, array) - Array of genre IDs

**Success Response (201):**
```json
{
  "message": "Video created successfully",
  "video": {
    "id": 1,
    "type": "movie",
    "title": "The Matrix",
    "description": "A computer hacker learns about the true nature of reality.",
    "thumbnail": "https://example.com/matrix.jpg",
    "banner": "https://example.com/matrix-banner.jpg",
    "cloudflare_video_uid": "abc123def456",
    "duration": 8160,
    "release_year": 1999,
    "age_rating": "R",
    "imdb_rating": 8.7,
    "is_published": true,
    "is_featured": true,
    "is_trending": false,
    "view_count": 0,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T10:00:00.000000Z",
    "categories": [
      {
        "id": 1,
        "name": "Action",
        "slug": "action",
        "pivot": {
          "video_id": 1,
          "category_id": 1
        }
      }
    ],
    "genres": [
      {
        "id": 1,
        "name": "Sci-Fi",
        "slug": "sci-fi",
        "pivot": {
          "video_id": 1,
          "genre_id": 1
        }
      }
    ]
  }
}
```

**Note:** For movies without a `cloudflare_video_uid`, a placeholder will be generated: `placeholder_xyz123`

---

### 7. Update Video (Admin Only)

Update an existing video.

**Endpoint:** `PUT /admin/videos/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Request Body (all fields optional):**
```json
{
  "title": "The Matrix Reloaded",
  "is_trending": true,
  "category_ids": [1, 2, 3],
  "genre_ids": [1]
}
```

**Success Response (200):**
```json
{
  "message": "Video updated successfully",
  "video": {
    "id": 1,
    "type": "movie",
    "title": "The Matrix Reloaded",
    "is_trending": true,
    "created_at": "2025-12-28T10:00:00.000000Z",
    "updated_at": "2025-12-28T12:00:00.000000Z",
    "categories": [...],
    "genres": [...]
  }
}
```

---

### 8. Delete Video (Admin Only)

Delete a video and its related data.

**Endpoint:** `DELETE /admin/videos/{id}`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Success Response (200):**
```json
{
  "message": "Video deleted successfully"
}
```

**Note:** This will also delete associated seasons and episodes (if any).

---

## Error Responses

### Common HTTP Status Codes

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication failed
- `403 Forbidden` - Insufficient permissions (not admin)
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed

### Validation Error Format (422)

```json
{
  "message": "The name field is required. (and 1 more error)",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```

### Unauthenticated Error (401)

```json
{
  "message": "Unauthenticated"
}
```

### Unauthorized Error (403)

```json
{
  "message": "Unauthorized. Admin access required."
}
```

### Not Found Error (404)

```json
{
  "message": "Video not found"
}
```

---

## Authentication Flow

1. **Send OTP:** `POST /auth/send-otp` with phone number
2. **Verify OTP:** `POST /auth/verify-otp` with phone + OTP → Receive token
3. **Use Token:** Include `Authorization: Bearer {token}` in all subsequent requests
4. **Logout:** `POST /auth/logout` to revoke token

---

## Admin Access

To access admin endpoints (`/api/admin/*`), you must:
1. Authenticate with a user that has `role: "admin"`
2. Include the admin token in the Authorization header

**Admin Test User:**
- Phone: `9999999`
- OTP: `123456`
- Role: `admin`

---

## Cloudflare Stream & Put.io Integration

### Admin: Test Cloudflare Connection

Check if Cloudflare Stream API credentials are configured.

**Endpoint:** `GET /admin/cloudflare/test-connection`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Cloudflare Stream is configured and ready",
  "configured": true,
  "details": {
    "account_id": "Set",
    "api_token": "Set",
    "customer_subdomain": "customer-1n8d31xiafu0ke30",
    "base_url": "https://api.cloudflare.com/client/v4/accounts/5a3a36ea1e43820958e54b7fa35987b9/stream"
  }
}
```

---

### Admin: Upload Video from Magnet Link (Complete Workflow)

Upload video using magnet link via Put.io → Cloudflare Stream workflow.

**Endpoint:** `POST /admin/cloudflare/upload-from-magnet`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "magnet_url": "magnet:?xt=urn:btih:...",
  "title": "My Video Title",
  "description": "Optional video description"
}
```

**Workflow:**
1. Adds magnet link to Put.io
2. Waits for Put.io to download the torrent (polls every 10 seconds, max 10 minutes)
3. Gets download URL from Put.io
4. Uploads to Cloudflare Stream from Put.io URL
5. Returns Cloudflare video UID

**Success Response (201):**
```json
{
  "message": "Video uploaded successfully via Put.io",
  "putio": {
    "transfer_id": 123456,
    "file_id": 789012,
    "file_name": "video.mp4",
    "file_size": 1073741824
  },
  "video": {
    "uid": "abc123def456",
    "status": "processing",
    "ready": false,
    "stream_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.m3u8",
    "thumbnail": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/thumbnails/thumbnail.jpg?time=1s",
    "embed_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/iframe"
  }
}
```

**Error Response - Transfer Failed (500):**
```json
{
  "message": "Transfer failed: Invalid torrent",
  "transfer_id": 123456
}
```

**Error Response - Timeout (500):**
```json
{
  "message": "Transfer timeout - still downloading after 10 minutes",
  "transfer_id": 123456
}
```

---

### Admin: Initiate Magnet Transfer (Async)

Start a Put.io transfer without waiting for completion.

**Endpoint:** `POST /admin/cloudflare/initiate-transfer`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "magnet_url": "magnet:?xt=urn:btih:..."
}
```

**Success Response (201):**
```json
{
  "message": "Transfer initiated successfully",
  "transfer": {
    "id": 123456,
    "name": "video.mp4",
    "status": "DOWNLOADING"
  }
}
```

---

### Admin: Check Transfer Status

Check the status of a Put.io transfer.

**Endpoint:** `GET /admin/cloudflare/transfer/{transferId}/status`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Transfer status retrieved",
  "transfer": {
    "id": 123456,
    "name": "video.mp4",
    "status": "DOWNLOADING",
    "percent_done": 45,
    "size": 1073741824,
    "downloaded": 483729408,
    "file_id": null
  }
}
```

**When Completed:**
```json
{
  "message": "Transfer status retrieved",
  "transfer": {
    "id": 123456,
    "name": "video.mp4",
    "status": "COMPLETED",
    "percent_done": 100,
    "size": 1073741824,
    "downloaded": 1073741824,
    "file_id": 789012
  }
}
```

---

### Admin: Upload Video from URL

Upload video directly to Cloudflare Stream from any URL.

**Endpoint:** `POST /admin/cloudflare/upload-from-url`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "video_url": "https://example.com/video.mp4",
  "title": "My Video Title",
  "description": "Optional video description"
}
```

**Success Response (201):**
```json
{
  "message": "Video upload initiated from URL",
  "video": {
    "uid": "abc123def456",
    "status": "processing",
    "ready": false,
    "stream_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.m3u8",
    "thumbnail": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/thumbnails/thumbnail.jpg?time=1s"
  }
}
```

---

### Admin: Upload Video File

Upload video file directly to Cloudflare Stream.

**Endpoint:** `POST /admin/cloudflare/upload-video`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body (form-data):**
```
video_file: [file] (mp4, mov, avi, mkv, webm - max 5GB)
title: "My Video Title"
description: "Optional video description"
```

**Success Response (201):**
```json
{
  "message": "Video uploaded successfully",
  "video": {
    "uid": "abc123def456",
    "status": "processing",
    "ready": false,
    "stream_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.m3u8",
    "thumbnail": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/thumbnails/thumbnail.jpg?time=1s",
    "embed_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/iframe"
  }
}
```

---

### Admin: Get Video Details

Get Cloudflare Stream video details.

**Endpoint:** `GET /admin/cloudflare/video/{videoUid}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Video retrieved successfully",
  "video": {
    "uid": "abc123def456",
    "status": "ready",
    "ready": true,
    "duration": 3600,
    "stream_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.m3u8",
    "dash_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.mpd",
    "thumbnail": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/thumbnails/thumbnail.jpg?time=1s",
    "embed_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/iframe"
  }
}
```

---

### Admin: Delete Video

Delete video from Cloudflare Stream.

**Endpoint:** `DELETE /admin/cloudflare/video/{videoUid}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Video deleted successfully"
}
```

---

### User: Get Stream URL

Get streaming URLs for a video.

**Endpoint:** `GET /stream/{videoUid}`

**No authentication required**

**Success Response (200):**
```json
{
  "message": "Stream URL retrieved",
  "video_uid": "abc123def456",
  "stream_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.m3u8",
  "dash_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/manifest/video.mpd",
  "thumbnail": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/thumbnails/thumbnail.jpg?time=1s",
  "embed_url": "https://customer-1n8d31xiafu0ke30.cloudflarestream.com/abc123def456/iframe"
}
```

---

## Notes

- All timestamps are in ISO 8601 format (UTC)
- All dates are in `Y-m-d H:i:s` format
- Cloudflare Stream is fully configured with real API credentials
- Put.io API token is configured for torrent downloads
- OTP is fixed at `123456` for development (will be dynamic in production)
- Phone numbers are 7 digits
- Maximum 5 profiles per user
- Magnet upload workflow polls every 10 seconds for up to 10 minutes

---

**Generated:** 2025-12-28
**API Version:** 1.0
**Laravel Version:** 12.x
