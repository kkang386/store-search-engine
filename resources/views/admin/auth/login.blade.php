<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search Admin Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-8">
    <div class="text-center mb-8">
        <div class="text-2xl font-bold text-gray-800">Search Admin</div>
        <div class="text-gray-400 text-sm mt-1">Sign in to the administration console</div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4 text-red-700 text-sm">
        {{ $errors->first() }}
    </div>
    @endif

    <form action="{{ route('admin.auth.authenticate') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input name="email" type="email" required value="{{ old('email') }}"
                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input name="password" type="password" required
                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input name="remember" type="checkbox"> Remember me
        </label>
        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            Sign In
        </button>
    </form>
</div>

</body>
</html>
