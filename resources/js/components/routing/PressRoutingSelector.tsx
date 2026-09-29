/**
 * PressRoutingSelector
 * ---------------------------------------------------------------------------
 * شاشة توزيع المطابع — قرار مساعد (decision support) مش قاعدة تلقائية
 *
 * As agreed: press assignment depends on cut size, colors, paper type,
 * quantity, AND each press's current schedule — which changes too often for
 * a fixed lookup table. So this component does NOT auto-assign a press. It
 * filters the press list down to the ones that CAN take this job, sorts them
 * by how free they currently are, and lets a human pick. The final choice is
 * always a manual click.
 *
 * IMPORTANT
 * ---------------------------------------------------------------------------
 * `currentBacklogDays` here is the whole capacity model for now: "how many
 * days of work this press already has queued." It's editable inline so staff
 * can keep it roughly current without a scheduling system. If/when Pantopack
 * gets real production scheduling data, replace this field's source with
 * that feed instead of manual entry — nothing else in this component needs
 * to change, since it only ever reads `currentBacklogDays` to sort/flag.
 * ---------------------------------------------------------------------------
 */

import { useCallback, useMemo, useState } from 'react';
import { AlertTriangle, Building2, CheckCircle2, ChevronDown, Factory, Palette, Scissors } from 'lucide-react';

// ============================================================================
// Types
// ============================================================================

export type CutFraction = '1/1' | '1/2' | '1/4' | '1/6' | '1/8';

export type PaperCategory =
  | 'duplex_grey_back'
  | 'duplex_white_back'
  | 'bristol_white_back'
  | 'kraft_liner'
  | 'couche'
  | 'triplex_board';

export interface Press {
  id: string;
  name: string;
  isInternal: boolean; // مطبعة داخلية ولا مقاول خارجي
  maxColors: number; // أقصى عدد ألوان تقدر تطبعه
  supportedCutFractions: CutFraction[]; // أحجام الفرخ اللي بتشتغل بيها
  supportedPaperCategories: PaperCategory[]; // فاضية = بتقبل كل أنواع الورق
  currentBacklogDays: number; // كام يوم شغل قدامها دلوقتي — مؤشر الطاقة التقريبي
  contactNote?: string; // رقم تليفون أو اسم المسؤول عندهم
}

export interface JobRoutingRequirements {
  cutFraction: CutFraction;
  printColors: number;
  paperCategory: PaperCategory;
  quantity: number;
}

interface PressRoutingSelectorProps {
  /** The press/subcontractor list, from the server. */
  presses: Press[];
  /** What this specific job needs, usually handed down from the pricing calculator. */
  requirements: JobRoutingRequirements;
  /** Called when staff manually picks a press for this job. */
  onSelectPress?: (press: Press) => void;
  /** Called when staff edit a press's backlog inline, so it can be saved. */
  onBacklogChange?: (pressId: string, days: number) => void;
  /** Press already assigned to this job, shown as selected. */
  initialSelectedPressId?: string | null;
  className?: string;
}

// ============================================================================
// Matching logic
// ============================================================================

interface PressMatch {
  press: Press;
  matches: boolean;
  reasons: string[]; // why it doesn't match, if it doesn't
}

function evaluatePress(press: Press, req: JobRoutingRequirements): PressMatch {
  const reasons: string[] = [];

  if (press.maxColors < req.printColors) {
    reasons.push(`أقصى ألوان عندها ${press.maxColors}، والشغلانة محتاجة ${req.printColors}`);
  }
  if (!press.supportedCutFractions.includes(req.cutFraction)) {
    reasons.push(`مش شغالة بمقاس ${req.cutFraction}`);
  }
  if (press.supportedPaperCategories.length > 0 && !press.supportedPaperCategories.includes(req.paperCategory)) {
    reasons.push('مش شغالة بنوع الورق ده');
  }

  return { press, matches: reasons.length === 0, reasons };
}

function backlogLevel(days: number): { label: string; color: string } {
  if (days <= 2) return { label: 'متاحة قريب', color: 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10' };
  if (days <= 5) return { label: 'مشغولة نسبيًا', color: 'text-amber-400 border-amber-500/30 bg-amber-500/10' };
  return { label: 'مشغولة جدًا', color: 'text-red-400 border-red-500/30 bg-red-500/10' };
}

const PAPER_CATEGORY_LABELS: Record<PaperCategory, string> = {
  duplex_grey_back: 'دوبلكس ظهر رمادي',
  duplex_white_back: 'دوبلكس ظهر أبيض',
  bristol_white_back: 'بريستول ظهر أبيض',
  kraft_liner: 'كرافت',
  couche: 'كوشيه',
  triplex_board: 'تريبلكس',
};

// ============================================================================
// Component
// ============================================================================

export default function PressRoutingSelector({
  presses,
  requirements,
  onSelectPress,
  onBacklogChange,
  initialSelectedPressId = null,
  className = '',
}: PressRoutingSelectorProps) {
  const [pressList, setPressList] = useState<Press[]>(presses);
  const [showUnmatched, setShowUnmatched] = useState(false);
  const [selectedPressId, setSelectedPressId] = useState<string | null>(initialSelectedPressId);

  const evaluated = useMemo(() => pressList.map((p) => evaluatePress(p, requirements)), [pressList, requirements]);

  const matched = useMemo(
    () => evaluated.filter((m) => m.matches).sort((a, b) => a.press.currentBacklogDays - b.press.currentBacklogDays),
    [evaluated]
  );
  const unmatched = useMemo(() => evaluated.filter((m) => !m.matches), [evaluated]);

  const handleUpdateBacklog = useCallback((pressId: string, days: number) => {
    setPressList((prev) => prev.map((p) => (p.id === pressId ? { ...p, currentBacklogDays: Math.max(0, days) } : p)));
    onBacklogChange?.(pressId, Math.max(0, days));
  }, [onBacklogChange]);

  const handleSelect = useCallback(
    (press: Press) => {
      setSelectedPressId(press.id);
      onSelectPress?.(press);
    },
    [onSelectPress]
  );

  return (
    <div dir="rtl" className={`bg-slate-950 border border-slate-800 rounded-2xl p-4 lg:p-6 text-slate-100 ${className}`}>
      <h2 className="text-base font-semibold mb-1">توزيع المطابع</h2>
      <p className="text-xs text-slate-500 mb-4">
        القائمة دي بتوريك المطابع اللي تقدر تستقبل الشغلانة دي دلوقتي — الاختيار النهائي عليك انت.
      </p>

      {/* Job requirements summary */}
      <div className="mb-4 flex flex-wrap gap-2">
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-800 px-3 py-1 text-xs">
          <Scissors className="w-3 h-3 text-slate-400" />
          مقاس {requirements.cutFraction}
        </span>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-800 px-3 py-1 text-xs">
          <Palette className="w-3 h-3 text-slate-400" />
          {requirements.printColors === 0 ? 'سادة' : `${requirements.printColors} لون`}
        </span>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-800 px-3 py-1 text-xs">
          {PAPER_CATEGORY_LABELS[requirements.paperCategory]}
        </span>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-800 px-3 py-1 text-xs">
          {requirements.quantity.toLocaleString('ar-EG')} قطعة
        </span>
      </div>

      {/* Matched presses */}
      <div className="space-y-2">
        {matched.length === 0 && (
          <div className="rounded-lg bg-red-950/30 border border-red-900/50 p-3 text-sm text-red-300 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0" />
            مفيش مطبعة مسجلة تقدر تستقبل الشغلانة دي بمواصفاتها الحالية.
          </div>
        )}

        {matched.map(({ press }) => {
          const backlog = backlogLevel(press.currentBacklogDays);
          const isSelected = selectedPressId === press.id;
          return (
            <div
              key={press.id}
              className={`rounded-xl border p-3 transition-colors ${
                isSelected ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-slate-900 border-slate-800'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2">
                  {press.isInternal ? (
                    <Factory className="w-4 h-4 text-sky-400" />
                  ) : (
                    <Building2 className="w-4 h-4 text-amber-400" />
                  )}
                  <div>
                    <div className="text-sm font-medium flex items-center gap-1.5">
                      {press.name}
                      {isSelected && <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />}
                    </div>
                    {press.contactNote && <div className="text-[11px] text-slate-500">{press.contactNote}</div>}
                  </div>
                </div>
                <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[11px] ${backlog.color}`}>
                  {backlog.label}
                </span>
              </div>

              <div className="mt-2.5 flex items-center justify-between gap-2">
                <div className="flex items-center gap-1.5 text-[11px] text-slate-400">
                  <span>الشغل الحالي عندها:</span>
                  <input
                    type="number"
                    min={0}
                    value={press.currentBacklogDays}
                    onChange={(e) => handleUpdateBacklog(press.id, Number(e.target.value))}
                    className="w-14 rounded bg-slate-800 border border-slate-700 px-1.5 py-1 text-xs text-center"
                  />
                  <span>يوم</span>
                </div>
                <button
                  onClick={() => handleSelect(press)}
                  className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                    isSelected ? 'bg-emerald-600 text-white' : 'bg-sky-600 hover:bg-sky-500 text-white'
                  }`}
                >
                  {isSelected ? 'مُختارة' : 'اختار دي'}
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Unmatched presses (collapsed by default) */}
      {unmatched.length > 0 && (
        <div className="mt-3">
          <button
            onClick={() => setShowUnmatched((v) => !v)}
            className="flex items-center gap-1 text-[11px] text-slate-500 hover:text-slate-300"
          >
            <ChevronDown className={`w-3.5 h-3.5 transition-transform ${showUnmatched ? 'rotate-180' : ''}`} />
            {unmatched.length} مطبعة مش مناسبة للشغلانة دي
          </button>
          {showUnmatched && (
            <div className="mt-2 space-y-1.5">
              {unmatched.map(({ press, reasons }) => (
                <div key={press.id} className="rounded-lg bg-slate-900/60 border border-slate-800 p-2.5 text-[11px] text-slate-500">
                  <span className="text-slate-400 font-medium">{press.name}</span> — {reasons.join(' · ')}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
