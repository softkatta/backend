<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Applications Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Job Applications — {{ now()->format('d M Y') }}</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Job</th>
                <th>Status</th>
                <th>Experience</th>
                <th>Applied</th>
            </tr>
        </thead>
        <tbody>
            @foreach($applications as $application)
            <tr>
                <td>{{ $application->name }}</td>
                <td>{{ $application->email }}</td>
                <td>{{ $application->career?->title }}</td>
                <td>{{ $application->status }}</td>
                <td>{{ $application->total_experience }}</td>
                <td>{{ $application->created_at?->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
