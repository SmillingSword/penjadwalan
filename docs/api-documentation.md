# Calendar & Scheduling Application API Documentation

## Overview

This is a multi-tenant calendar and scheduling application built with Laravel. The API provides endpoints for managing calendars, events, participants, and reminders with proper tenant isolation.

## Authentication

All API endpoints require authentication using Laravel Sanctum. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Tenant Isolation

All requests must include the organization ID in the header:

```
X-Organization-ID: {organization-uuid}
```

## Base URL

```
/api
```

## Endpoints

### User Information

#### Get Current User
```
GET /user
```

Returns information about the currently authenticated user.

**Response:**
```json
{
  "id": "uuid",
  "name": "John Doe",
  "email": "john@example.com",
  "timezone": "Asia/Jakarta",
  "locale": "id-ID",
  "email_verified_at": "2024-01-15T10:00:00.000000Z",
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

### Calendars

#### List Calendars
```
GET /calendars
```

Returns a list of calendars for the current organization.

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": "uuid",
      "owner_user_id": "uuid",
      "name": "My Calendar",
      "description": "Personal calendar",
      "color": "#FF0000",
      "is_default": false,
      "is_public": true,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

#### Create Calendar
```
POST /calendars
```

**Request Body:**
```json
{
  "name": "New Calendar",
  "description": "Calendar description",
  "color": "#00FF00",
  "is_default": false,
  "is_public": true
}
```

**Response:** Calendar object (201 Created)

#### Get Calendar
```
GET /calendars/{id}
```

**Response:** Calendar object

#### Update Calendar
```
PUT /calendars/{id}
```

**Request Body:** Same as create calendar

**Response:** Updated calendar object

#### Delete Calendar
```
DELETE /calendars/{id}
```

**Response:**
```json
{
  "message": "Calendar deleted successfully"
}
```

#### Get Calendar Events
```
GET /calendars/{id}/events
```

Returns all events for a specific calendar.

### Events

#### List Events
```
GET /events
```

Returns a list of events for the current organization.

**Query Parameters:**
- `start_date` (optional): Filter events starting from this date (Y-m-d format)
- `end_date` (optional): Filter events ending before this date (Y-m-d format)
- `calendar_id` (optional): Filter events by calendar ID

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "calendar_id": "uuid",
      "title": "Meeting",
      "description_md": "Team meeting",
      "location": "Conference Room A",
      "meeting_link": "https://zoom.us/j/123456789",
      "start_at": "2024-01-15T10:00:00.000000Z",
      "end_at": "2024-01-15T11:00:00.000000Z",
      "all_day": false,
      "timezone": "Asia/Jakarta",
      "rrule": null,
      "exdates": [],
      "is_private": false,
      "created_at": "2024-01-15T09:00:00.000000Z",
      "updated_at": "2024-01-15T09:00:00.000000Z",
      "calendar": {
        "id": "uuid",
        "name": "Work Calendar"
      },
      "participants": [
        {
          "id": "uuid",
          "email": "participant@example.com",
          "name": "Participant Name",
          "status": "invited",
          "role": "required"
        }
      ],
      "reminders": [
        {
          "id": "uuid",
          "method": "email",
          "minutes_before": 15
        }
      ]
    }
  ]
}
```

#### Create Event
```
POST /events
```

**Request Body:**
```json
{
  "calendar_id": "uuid",
  "title": "New Event",
  "description_md": "Event description in markdown",
  "location": "Meeting Room",
  "meeting_link": "https://zoom.us/j/123456789",
  "start_at": "2024-01-15 10:00:00",
  "end_at": "2024-01-15 11:00:00",
  "all_day": false,
  "timezone": "Asia/Jakarta",
  "rrule": "FREQ=WEEKLY;BYDAY=MO",
  "is_private": false,
  "participants": [
    {
      "email": "participant@example.com",
      "name": "Participant Name",
      "role": "required"
    }
  ],
  "reminders": [
    {
      "method": "email",
      "minutes_before": 15
    }
  ]
}
```

**Response:** Event object (201 Created)

#### Get Event
```
GET /events/{id}
```

**Response:** Event object with relationships

#### Update Event
```
PUT /events/{id}
```

**Request Body:** Same as create event

**Response:** Updated event object

#### Delete Event
```
DELETE /events/{id}
```

**Response:**
```json
{
  "message": "Event deleted successfully"
}
```

#### Update Event Participants
```
PUT /events/{id}/participants
```

**Request Body:**
```json
{
  "participants": [
    {
      "email": "new-participant@example.com",
      "name": "New Participant",
      "role": "optional"
    }
  ]
}
```

**Response:**
```json
{
  "message": "Participants updated successfully"
}
```

## Data Types

### Calendar Object
- `id`: UUID - Unique identifier
- `organization_id`: UUID - Organization this calendar belongs to
- `owner_user_id`: UUID - User who owns this calendar
- `name`: String - Calendar name
- `description`: String (nullable) - Calendar description
- `color`: String - Hex color code (e.g., "#FF0000")
- `is_default`: Boolean - Whether this is the default calendar
- `is_public`: Boolean - Whether this calendar is public
- `created_at`: ISO 8601 timestamp
- `updated_at`: ISO 8601 timestamp

### Event Object
- `id`: UUID - Unique identifier
- `calendar_id`: UUID - Calendar this event belongs to
- `title`: String - Event title
- `description_md`: String (nullable) - Event description in Markdown
- `location`: String (nullable) - Event location
- `meeting_link`: String (nullable) - Online meeting link
- `start_at`: ISO 8601 timestamp - Event start time
- `end_at`: ISO 8601 timestamp - Event end time
- `all_day`: Boolean - Whether this is an all-day event
- `timezone`: String - Timezone for the event
- `rrule`: String (nullable) - Recurrence rule (RFC 5545)
- `exdates`: Array - Exception dates for recurring events
- `is_private`: Boolean - Whether this event is private
- `created_at`: ISO 8601 timestamp
- `updated_at`: ISO 8601 timestamp

### Event Participant Object
- `id`: UUID - Unique identifier
- `event_id`: UUID - Event this participant belongs to
- `email`: String - Participant email
- `name`: String (nullable) - Participant name
- `status`: Enum - invited, accepted, declined, tentative
- `role`: Enum - required, optional

### Reminder Object
- `id`: UUID - Unique identifier
- `event_id`: UUID - Event this reminder belongs to
- `method`: Enum - email, push
- `minutes_before`: Integer - Minutes before event to send reminder

## Error Responses

### 400 Bad Request
```json
{
  "error": "Organization ID is required"
}
```

### 401 Unauthorized
```json
{
  "error": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "error": "Access denied to this organization"
}
```

### 404 Not Found
```json
{
  "error": "Resource not found"
}
```

### 422 Unprocessable Entity
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "start_at": ["The start at field is required."]
  }
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal server error"
}
```

## Rate Limiting

API requests are rate limited to prevent abuse. The current limits are:
- 60 requests per minute for authenticated users
- 10 requests per minute for unauthenticated requests

## Pagination

List endpoints support pagination using Laravel's standard pagination:

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

**Response includes pagination metadata:**
```json
{
  "data": [...],
  "links": {
    "first": "http://example.com/api/events?page=1",
    "last": "http://example.com/api/events?page=10",
    "prev": null,
    "next": "http://example.com/api/events?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
