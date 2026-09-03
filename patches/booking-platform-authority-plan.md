# Booking platform authority cutover

Temporary implementation notes for the isolated connector remediation. WordPress must persist the Gen 2 StayBooking id in `dg_booking_platform_id`, include it as `platform_id` in booking payloads, and resolve PATCH/update requests by that canonical id before falling back to legacy WP row ids during migration.
