<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Code;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCodeController extends Controller
{
    public function index(Request $request)
    {
        $codes = Code::query()
            ->select('id', 'slug', 'title_ar', 'title_fr', 'type', 'status',
                     'official_number', 'total_articles', 'promulgation_date', 'created_at')
            ->when($request->input('q'), fn($q, $search) =>
                $q->where('title_ar', 'like', "%$search%")
                  ->orWhere('title_fr', 'like', "%$search%")
            )
            ->when($request->input('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('title_ar')
            ->paginate(20);

        return response()->json($codes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title_ar'         => 'required|string|max:255',
            'title_fr'         => 'nullable|string|max:255',
            'type'             => 'required|in:constitution,organic_law,ordinary_law,code,decree_law,decree,order,circular,international_treaty',
            'official_number'  => 'nullable|string|max:50',
            'promulgation_date'=> 'nullable|date',
            'status'           => 'sometimes|in:in_force,abrogated,amended,draft',
            'source_url'       => 'nullable|url|max:2000',
        ]);

        $data['id']   = Str::uuid();
        $data['slug'] = $this->uniqueSlug($data['title_fr'] ?? $data['title_ar']);

        $code = Code::create($data);

        return response()->json(['message' => 'تم إنشاء القانون', 'code' => $code], 201);
    }

    public function update(Request $request, Code $code)
    {
        $data = $request->validate([
            'title_ar'         => 'sometimes|string|max:255',
            'title_fr'         => 'nullable|string|max:255',
            'type'             => 'sometimes|in:constitution,organic_law,ordinary_law,code,decree_law,decree,order,circular,international_treaty',
            'status'           => 'sometimes|in:in_force,abrogated,amended,draft',
            'official_number'  => 'nullable|string|max:50',
            'promulgation_date'=> 'nullable|date',
            'source_url'       => 'nullable|url|max:2000',
        ]);

        $code->update($data);

        return response()->json(['message' => 'تم تحديث القانون', 'code' => $code]);
    }

    public function destroy(Code $code)
    {
        $articlesCount = $code->articles()->count();
        $code->articles()->delete();
        $code->delete();

        return response()->json([
            'message' => "تم حذف القانون و{$articlesCount} مادة مرتبطة به",
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'code-' . Str::random(6);
        $slug = $base;
        $i    = 2;
        while (Code::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
