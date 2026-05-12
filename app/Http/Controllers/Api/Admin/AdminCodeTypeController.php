<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodeType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCodeTypeController extends Controller
{
    public function index()
    {
        $types = CodeType::orderBy('sort_order')->orderBy('id')->get();

        // Append codes_count
        $types->each(function ($t) {
            $t->codes_count = \App\Models\Code::where('type', $t->slug)->count();
        });

        return response()->json($types);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name_ar'    => 'required|string|max:100',
            'name_fr'    => 'nullable|string|max:100',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['slug']       = Str::slug($data['name_ar'] . '-' . Str::random(4), '-');
        $data['color']      = $data['color'] ?? 'slate';
        $data['sort_order'] = $data['sort_order'] ?? (CodeType::max('sort_order') + 1);

        $type = CodeType::create($data);
        $type->codes_count = 0;

        return response()->json($type, 201);
    }

    public function update(Request $request, CodeType $codeType)
    {
        $data = $request->validate([
            'name_ar'    => 'sometimes|required|string|max:100',
            'name_fr'    => 'nullable|string|max:100',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $codeType->update($data);
        $codeType->codes_count = \App\Models\Code::where('type', $codeType->slug)->count();

        return response()->json($codeType);
    }

    public function destroy(CodeType $codeType)
    {
        $count = \App\Models\Code::where('type', $codeType->slug)->count();
        if ($count > 0) {
            return response()->json([
                'error' => "لا يمكن حذف هذا النوع، هناك {$count} قانون مرتبط به"
            ], 422);
        }

        $codeType->delete();
        return response()->json(null, 204);
    }
}
