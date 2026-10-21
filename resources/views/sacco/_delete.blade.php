<form method="POST" action="{{ $action }}" onsubmit="return confirm('Remove this?')" class="inline">
    @csrf
    @method('DELETE')
    <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
</form>
