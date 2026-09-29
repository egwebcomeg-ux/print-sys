<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\PaperGrammage;
use App\Models\PaperType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaperGrammageController extends Controller
{
    public function store(Request $request, PaperType $paperType): RedirectResponse
    {
        $data = $request->validate([
            'gsm' => [
                'required', 'integer', 'min:40', 'max:1000',
                Rule::unique('paper_grammages')->where('paper_type_id', $paperType->id),
            ],
        ], ['gsm.unique' => 'الجرام ده موجود بالفعل للنوع ده']);

        $paperType->grammages()->create($data);
        $this->toast('تم إضافة الجرام');

        return back();
    }

    public function destroy(PaperGrammage $grammage): RedirectResponse
    {
        if ($this->deleteSafely(fn () => $grammage->delete(), 'مينفعش تمسح جرام مستخدم في شغلانات')) {
            $this->toast('تم مسح الجرام');
        }

        return back();
    }
}
