@section('content')
<h3>
  <a href="{{ route("item.view", array("id"=>$item->id)) }}">{{ $item->name }}</a>
@if (count($item->disassembly)>0)
 can be disassembled to obtain following items.<br>
@else
 can't be disassembled.
@endif
</h3>
<br>
<div class="row">
<div class="col-sm-4">
@foreach ($item->disassembly as $recipe)
  {{link_to_route("item.view", $recipe->result->name, array("id"=>$recipe->result->id))}}<br>
  Skills used: {{ $recipe->skill_used }} <br>
  Required skills: {{ $recipe->skills_required }} <br>
  Difficulty: {{ $recipe->difficulty }}<br>
  Reversible: {{ $recipe->reversible }}<br>
  Learn Automatically: {{ $recipe->autolearn }}<br>
  Time to complete: {{ $recipe->time }}<br>
  @if ($recipe->hasTools)
  {{$recipe->tools}}<br>
  @endif

  @if ($recipe->hasComponents)
  {{$recipe->components}}<br>
  @endif
<br>
@endforeach
</div>
</div>
@stop
