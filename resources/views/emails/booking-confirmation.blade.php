<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .booking-id {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
            border: 2px dashed #667eea;
        }
        .section {
            margin: 25px 0;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
            text-align: right;
        }
        .ticket-list {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .ticket-item {
            padding: 5px 0;
            font-family: 'Courier New', monospace;
            color: #667eea;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            margin-top: 10px;
        }
        .alert-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-box h3 {
            margin-top: 0;
            color: #856404;
        }
        .alert-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Booking Confirmed!</h1>
            <p style="margin: 5px 0 0 0;">Your adventure awaits</p>
        </div>

        <div class="booking-id">
            Booking ID: {{ $booking['booking_id'] }}
        </div>

        <p style="font-size: 16px; color: #28a745; text-align: center; font-weight: bold;">
            🎉 Payment Successful - Your tickets are ready!
        </p>

        <!-- Attraction Information -->
        <div class="section">
            <div class="section-title">🏛️ Attraction Details</div>
            <div class="info-row">
                <span class="info-label">Attraction:</span>
                <span class="info-value">{{ $booking['attraction_name'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span class="info-value">{{ $booking['category'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit Date:</span>
                <span class="info-value">{{ $booking['visit_date'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Opening Hours:</span>
                <span class="info-value">{{ $booking['opening_hours'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Duration:</span>
                <span class="info-value">~{{ $booking['duration_minutes'] }} minutes</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value"><span class="status-badge">CONFIRMED</span></span>
            </div>
        </div>

        <!-- Tickets -->
        <div class="section">
            <div class="section-title">🎫 Your Tickets</div>
            <div class="info-row">
                <span class="info-label">Number of Tickets:</span>
                <span class="info-value">{{ $booking['number_of_tickets'] }}</span>
            </div>
            <div class="ticket-list">
                <strong>Ticket Numbers:</strong>
                @foreach($booking['ticket_numbers'] as $index => $ticketNumber)
                    <div class="ticket-item">{{ $index + 1 }}. {{ $ticketNumber }}</div>
                @endforeach
            </div>
        </div>

        <!-- Payment Information -->
        <div class="section">
            <div class="section-title">💳 Payment Details</div>
            <div class="info-row">
                <span class="info-label">Price per Ticket:</span>
                <span class="info-value">{{ $booking['price_per_ticket'] }} {{ $booking['currency'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Transaction ID:</span>
                <span class="info-value" style="font-family: 'Courier New', monospace;">{{ $transactionId }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Confirmed At:</span>
                <span class="info-value">{{ $booking['confirmed_at'] }}</span>
            </div>
            <div class="total-amount">
                Total Paid: {{ $booking['total_amount'] }} {{ $booking['currency'] }}
            </div>
        </div>

        <!-- Visitor Information -->
        <div class="section">
            <div class="section-title">👤 Visitor Information</div>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $booking['visitor_name'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $booking['visitor_email'] }}</span>
            </div>
        </div>

        <!-- What's Included -->
        <div class="section">
            <div class="section-title">📦 What's Included</div>
            <p>{{ $booking['booking_details'] }}</p>
        </div>

        <!-- Important Information -->
        <div class="alert-box">
            <h3>💡 Important Information</h3>
            <ul>
                <li>Save this email and your booking ID for reference</li>
                <li>Present your ticket numbers at the entrance (printed or on your phone)</li>
                <li>Please arrive 15 minutes before your scheduled time</li>
                <li>Keep your tickets safe - they cannot be replaced if lost</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for booking with us!</strong></p>
            <p>Have a wonderful visit! 🎉</p>
            <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
            <p>This is an automated confirmation email.</p>
            <p>Tourism Booking System &copy; {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>

