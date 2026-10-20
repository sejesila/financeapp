<form method="POST" action="{{ route('retirement.entries.destroy', ['fund' => $fund, 'entry' => $entry]) }}"
      onsubmit="return confirm('Remove this entry?')" class="inline">
    @csrf
    @method('DELETE')
    <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
</form>
