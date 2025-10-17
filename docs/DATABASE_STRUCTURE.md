# Calendar Database Structure

## Booking Table

The `booking` table stores appointment/booking information with the following structure:

### Columns:
- `id_specialist` - References the specialist handling the booking
- `id_work_place` - References the working point/location
- `booking_start_datetime` - The start date and time of the booking
- `booking_end_datetime` - The end date and time of the booking
- `day_of_creation` - When the booking was created
- `clien_full_name` - Client's full name
- `received_call_phone_nr` - Phone number that called
- `received_call_date` - When the call was received
- `client_contact_phone_nr` - Client's contact phone number
- `status` - (if exists) Booking status

### Key Relationships:
- `id_specialist` links to `specialists.unic_id`
- `id_work_place` links to `working_points.unic_id`

## Specialists Table

The `specialists` table stores specialist/employee information:

### Key Columns:
- `unic_id` - Unique identifier
- `organisation_id` - Links to organisation
- `name` - Specialist's name
- `role` - Role/position

## Working Points Table

The `working_points` table stores location/workplace information:

### Key Columns:
- `unic_id` - Unique identifier
- `organisation_id` - Links to organisation
- `name_of_the_place` - Location name
- `supervisor_user_id` - User who supervises this location

## Specialist Time Off Table

The `specialist_time_off` table stores days off for specialists:

### Columns:
- `id` - Primary key
- `specialist_id` - References specialist
- `workpoint_id` - References working point
- `date_off` - The date that's off
- `start_time` - Start time (typically 00:01:00)
- `end_time` - End time (typically 23:59:00)
- `created_at` - When the record was created

## Working Program Table

The `working_program` table stores work schedules:

### Key Columns:
- Links specialists to working points
- Defines working hours and days