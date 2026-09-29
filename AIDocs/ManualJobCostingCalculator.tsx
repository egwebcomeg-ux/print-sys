/**
 * ManualJobCostingCalculator
 * ---------------------------------------------------------------------------
 * حساب يدوي لتكلفة الشغلانات الورقية (مش صناديق/علب بالضرورة)
 *
 * For jobs where the cut size is already known (flyers, notebooks, booklets,
 * general paper/packaging sheets — not die-cut boxes) and there's no need for
 * dieline or imposition math: the user types paper line items (type, grammage,
 * sheet size, sheet count) and the tool computes weight + paper cost from
 * price-per-ton, the same way QuickBoxPricingCalculator does. On top of that,
 * the user adds free-form cost lines (printing, finishing, shipping, etc.)
 * and a manual profit margin, exactly matching how margin is added elsewhere
 * in this system (typed case-by-case, not a fixed rule).
 *
 * This file is intentionally self-contained (its own small paper catalogue
 * and helpers) so it can live on its own page/route. In production, both this
 * and QuickBoxPricingCalculator should read the SAME paper catalogue (e.g.
 * from one shared API/state) rather than each keeping its own copy.
 * ---------------------------------------------------------------------------
 */

import React, { useCallback, useMemo, useState } from 'react';
import { Check, Copy, Plus, Trash2 } from 'lucide-react';

// ============================================================================
// Types (kept field-compatible with QuickBoxPricingCalculator's paper model)
// ============================================================================

export interface PaperSupplierPrice {
  id: string;
  supplierName: string;
  pricePerTonEgp: number;
}

export interface PaperGrammageOption {
  id: string;
  gsm: number;
  prices: PaperSupplierPrice[];
}

export type PaperCategory =
  | 'duplex_grey_back'
  | 'duplex_white_back'
  | 'bristol_white_back'
  | 'kraft_liner'
  | 'couche'
  | 'triplex_board';

export interface PaperType {
  id: string;
  name: string;
  category: PaperCategory;
  standardSheetSize: { widthCm: number; heightCm: number };
  grammages: PaperGrammageOption[];
}

/** One paper line item the user types manually: paper + sheet size + count. */
export interface ManualLineItem {
  id: string;
  label: string; // اسم البند، مثال: "غلاف" أو "متن الكتيب"
  paperTypeId: string;
  grammageId: string;
  supplierPriceId: string;
  sheetWidthCm: number;
  sheetHeightCm: number;
  sheetsCount: number; // عدد الأفرخ
}

/** A free-form extra cost line (printing, finishing, shipping...). */
export interface ManualCostLine {
  id: string;
  label: string;
  amountEgp: number;
}

export interface ManualJobQuote {
  lineItems: ManualLineItem[];
  costLines: ManualCostLine[];
  totalPaperWeightKg: number;
  totalPaperCostEgp: number;
  totalManualCostsEgp: number;
  baseCostEgp: number;
  marginPercent: number;
  finalPriceEgp: number;
  producedQuantity: number | null;
  unitPriceEgp: number | null;
}

interface ManualJobCostingCalculatorProps {
  papers?: PaperType[];
  onConfirmOrder?: (quote: ManualJobQuote) => void;
  className?: string;
}

// ============================================================================
// Sample paper catalogue — same shape/placeholders as QuickBoxPricingCalculator.
// Replace with your real, shared catalogue (ideally the same one both tools use).
// ============================================================================

/** Generates 2–3 supplier price entries around a base price. PLACEHOLDER spread — replace with real vendor quotes. */
function sp(prefix: string, basePriceEgp: number): PaperSupplierPrice[] {
  return [
    { id: `${prefix}-a`, supplierName: 'مصنع الأهرام للورق', pricePerTonEgp: Math.round(basePriceEgp * 0.98) },
    { id: `${prefix}-b`, supplierName: 'شركة النصر للورق', pricePerTonEgp: basePriceEgp },
    { id: `${prefix}-c`, supplierName: 'مستورد المعادي', pricePerTonEgp: Math.round(basePriceEgp * 1.04) },
  ];
}

const PAPER_TYPES_DATABASE: PaperType[] = [
  {
    id: 'pt1', name: 'دوبلكس ظهر رمادي', category: 'duplex_grey_back', standardSheetSize: { widthCm: 70, heightCm: 100 },
    grammages: [
      { id: 'g1', gsm: 250, prices: sp('g1', 14000) },
      { id: 'g2', gsm: 300, prices: sp('g2', 14500) },
      { id: 'g3', gsm: 350, prices: sp('g3', 15200) },
      { id: 'g4', gsm: 400, prices: sp('g4', 16000) },
    ],
  },
  {
    id: 'pt2', name: 'دوبلكس ظهر أبيض', category: 'duplex_white_back', standardSheetSize: { widthCm: 70, heightCm: 100 },
    grammages: [
      { id: 'g5', gsm: 250, prices: sp('g5', 15500) },
      { id: 'g6', gsm: 300, prices: sp('g6', 16200) },
      { id: 'g7', gsm: 350, prices: sp('g7', 17000) },
    ],
  },
  {
    id: 'pt3', name: 'بريستول ظهر أبيض', category: 'bristol_white_back', standardSheetSize: { widthCm: 70, heightCm: 100 },
    grammages: [
      { id: 'g8', gsm: 250, prices: sp('g8', 17500) },
      { id: 'g9', gsm: 300, prices: sp('g9', 18300) },
      { id: 'g10', gsm: 350, prices: sp('g10', 19000) },
    ],
  },
  {
    id: 'pt4', name: 'كرافت بني نقي', category: 'kraft_liner', standardSheetSize: { widthCm: 70, heightCm: 100 },
    grammages: [
      { id: 'g11', gsm: 150, prices: sp('g11', 12500) },
      { id: 'g12', gsm: 200, prices: sp('g12', 13000) },
      { id: 'g13', gsm: 250, prices: sp('g13', 13800) },
    ],
  },
  {
    id: 'pt5', name: 'كوشيه لامع', category: 'couche', standardSheetSize: { widthCm: 70, heightCm: 100 },
    grammages: [
      { id: 'g14', gsm: 150, prices: sp('g14', 16000) },
      { id: 'g15', gsm: 200, prices: sp('g15', 16800) },
      { id: 'g16', gsm: 250, prices: sp('g16', 17600) },
    ],
  },
];

// ============================================================================
// Helpers
// ============================================================================

function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

/** The cheapest supplier price for a grammage, or null if it has none registered yet. */
function cheapestPrice(grammage: PaperGrammageOption | undefined): PaperSupplierPrice | null {
  if (!grammage || grammage.prices.length === 0) return null;
  return [...grammage.prices].sort((a, b) => a.pricePerTonEgp - b.pricePerTonEgp)[0];
}

/** Weight (kg) and cost (EGP) for one line item, from its own sheet size/grammage/supplier price/count. */
function computeLineItem(item: ManualLineItem, paperCatalog: PaperType[]) {
  const paper = paperCatalog.find((p) => p.id === item.paperTypeId);
  const grammage = paper?.grammages.find((g) => g.id === item.grammageId);
  const price = grammage?.prices.find((pr) => pr.id === item.supplierPriceId) ?? cheapestPrice(grammage);
  if (!paper || !grammage || !price || item.sheetWidthCm <= 0 || item.sheetHeightCm <= 0 || item.sheetsCount <= 0) {
    return { weightKg: 0, costEgp: 0 };
  }
  const areaM2 = (item.sheetWidthCm / 100) * (item.sheetHeightCm / 100);
  const sheetWeightKg = (areaM2 * grammage.gsm) / 1000;
  const totalWeightKg = sheetWeightKg * item.sheetsCount;
  const costEgp = totalWeightKg * (price.pricePerTonEgp / 1000);
  return { weightKg: totalWeightKg, costEgp };
}

let localIdCounter = 0;
function nextLocalId(prefix: string): string {
  localIdCounter += 1;
  return `${prefix}-${Date.now()}-${localIdCounter}`;
}

function makeLineItem(paperCatalog: PaperType[], label: string): ManualLineItem {
  const firstPaper = paperCatalog[0];
  const firstGrammage = firstPaper?.grammages[0];
  return {
    id: nextLocalId('line'),
    label,
    paperTypeId: firstPaper?.id ?? '',
    grammageId: firstGrammage?.id ?? '',
    supplierPriceId: cheapestPrice(firstGrammage)?.id ?? '',
    sheetWidthCm: firstPaper?.standardSheetSize.widthCm ?? 70,
    sheetHeightCm: firstPaper?.standardSheetSize.heightCm ?? 100,
    sheetsCount: 1000,
  };
}

// ============================================================================
// Component
// ============================================================================

export default function ManualJobCostingCalculator({
  papers = PAPER_TYPES_DATABASE,
  onConfirmOrder,
  className = '',
}: ManualJobCostingCalculatorProps) {
  const [paperCatalog] = useState<PaperType[]>(papers);
  const [lineItems, setLineItems] = useState<ManualLineItem[]>([makeLineItem(papers, 'بند 1')]);
  const [costLines, setCostLines] = useState<ManualCostLine[]>([
    { id: nextLocalId('cost'), label: 'طباعة', amountEgp: 0 },
  ]);
  const [marginPercent, setMarginPercent] = useState(20);
  const [producedQuantity, setProducedQuantity] = useState<number | ''>('');
  const [copied, setCopied] = useState(false);

  // --- Line item actions ---------------------------------------------------
  const handleAddLineItem = useCallback(() => {
    setLineItems((prev) => [...prev, makeLineItem(paperCatalog, `بند ${prev.length + 1}`)]);
  }, [paperCatalog]);

  const handleRemoveLineItem = useCallback((id: string) => {
    setLineItems((prev) => (prev.length > 1 ? prev.filter((li) => li.id !== id) : prev));
  }, []);

  const handleUpdateLineItem = useCallback((id: string, patch: Partial<ManualLineItem>) => {
    setLineItems((prev) => prev.map((li) => (li.id === id ? { ...li, ...patch } : li)));
  }, []);

  // --- Cost line actions -----------------------------------------------------
  const handleAddCostLine = useCallback(() => {
    setCostLines((prev) => [...prev, { id: nextLocalId('cost'), label: '', amountEgp: 0 }]);
  }, []);

  const handleRemoveCostLine = useCallback((id: string) => {
    setCostLines((prev) => prev.filter((cl) => cl.id !== id));
  }, []);

  const handleUpdateCostLine = useCallback((id: string, patch: Partial<ManualCostLine>) => {
    setCostLines((prev) => prev.map((cl) => (cl.id === id ? { ...cl, ...patch } : cl)));
  }, []);

  // --- Computation ------------------------------------------------------
  const lineResults = useMemo(
    () => lineItems.map((item) => ({ item, ...computeLineItem(item, paperCatalog) })),
    [lineItems, paperCatalog]
  );

  const calc = useMemo(() => {
    const totalPaperWeightKg = lineResults.reduce((sum, r) => sum + r.weightKg, 0);
    const totalPaperCostEgp = lineResults.reduce((sum, r) => sum + r.costEgp, 0);
    const totalManualCostsEgp = costLines.reduce((sum, cl) => sum + (Number.isFinite(cl.amountEgp) ? cl.amountEgp : 0), 0);
    const baseCostEgp = totalPaperCostEgp + totalManualCostsEgp;
    const marginAmountEgp = baseCostEgp * (marginPercent / 100);
    const finalPriceEgp = baseCostEgp + marginAmountEgp;
    const qty = typeof producedQuantity === 'number' && producedQuantity > 0 ? producedQuantity : null;
    const unitPriceEgp = qty ? round2(finalPriceEgp / qty) : null;

    return {
      totalPaperWeightKg: round2(totalPaperWeightKg),
      totalPaperCostEgp: round2(totalPaperCostEgp),
      totalManualCostsEgp: round2(totalManualCostsEgp),
      baseCostEgp: round2(baseCostEgp),
      marginAmountEgp: round2(marginAmountEgp),
      finalPriceEgp: round2(finalPriceEgp),
      unitPriceEgp,
    };
  }, [lineResults, costLines, marginPercent, producedQuantity]);

  // --- Copy to clipboard ---------------------------------------------------
  const copyText = useMemo(() => {
    const lines: string[] = [];
    lineResults.forEach(({ item, weightKg, costEgp }) => {
      const paper = paperCatalog.find((p) => p.id === item.paperTypeId);
      const grammage = paper?.grammages.find((g) => g.id === item.grammageId);
      const price = grammage?.prices.find((pr) => pr.id === item.supplierPriceId);
      lines.push(
        `- ${item.label}: ${item.sheetsCount.toLocaleString('ar-EG')} فرخ ${paper?.name ?? ''} ${grammage ? `${grammage.gsm} جرام` : ''}${price ? ` (${price.supplierName})` : ''} (${item.sheetWidthCm}×${item.sheetHeightCm} سم) — ${round2(weightKg)} كجم — ${round2(costEgp)} ج`
      );
    });
    costLines.forEach((cl) => {
      if (cl.label) lines.push(`- ${cl.label}: ${cl.amountEgp.toLocaleString('ar-EG')} ج`);
    });
    lines.push(`- إجمالي التكلفة: ${calc.baseCostEgp.toLocaleString('ar-EG')} ج`);
    lines.push(`- نسبة الربح ${marginPercent}%: ${calc.marginAmountEgp.toLocaleString('ar-EG')} ج`);
    lines.push(`- السعر النهائي: ${calc.finalPriceEgp.toLocaleString('ar-EG')} ج`);
    if (calc.unitPriceEgp !== null) {
      lines.push(`- سعر القطعة: ${calc.unitPriceEgp.toFixed(2)} ج`);
    }
    return lines.join('\n');
  }, [lineResults, paperCatalog, costLines, calc, marginPercent]);

  const handleCopy = useCallback(() => {
    navigator.clipboard?.writeText(copyText).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    });
  }, [copyText]);

  const handleConfirm = useCallback(() => {
    onConfirmOrder?.({
      lineItems,
      costLines,
      totalPaperWeightKg: calc.totalPaperWeightKg,
      totalPaperCostEgp: calc.totalPaperCostEgp,
      totalManualCostsEgp: calc.totalManualCostsEgp,
      baseCostEgp: calc.baseCostEgp,
      marginPercent,
      finalPriceEgp: calc.finalPriceEgp,
      producedQuantity: typeof producedQuantity === 'number' ? producedQuantity : null,
      unitPriceEgp: calc.unitPriceEgp,
    });
  }, [lineItems, costLines, calc, marginPercent, producedQuantity, onConfirmOrder]);

  return (
    <div dir="rtl" className={`bg-slate-950 border border-slate-800 rounded-2xl p-4 lg:p-6 text-slate-100 ${className}`}>
      <div className="mb-5 flex items-center justify-between">
        <h2 className="text-base font-semibold">حساب يدوي — بنود ورق + مصاريف</h2>
        <button
          onClick={handleCopy}
          className="flex items-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 px-2.5 py-1.5 text-xs transition-colors"
        >
          {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
          {copied ? 'تم النسخ!' : 'نسخ النص'}
        </button>
      </div>

      {/* Paper line items */}
      <div className="space-y-3 mb-5">
        <div className="text-xs text-slate-400">بنود الورق</div>
        {lineResults.map(({ item, weightKg, costEgp }) => {
          const paper = paperCatalog.find((p) => p.id === item.paperTypeId);
          return (
            <div key={item.id} className="rounded-xl bg-slate-900 border border-slate-800 p-3">
              <div className="flex items-center justify-between mb-3">
                <input
                  type="text"
                  value={item.label}
                  onChange={(e) => handleUpdateLineItem(item.id, { label: e.target.value })}
                  className="bg-transparent text-sm font-medium border-b border-transparent focus:border-slate-600 focus:outline-none"
                />
                <button
                  onClick={() => handleRemoveLineItem(item.id)}
                  className="text-red-400 hover:text-red-300"
                  disabled={lineItems.length <= 1}
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">نوع الورق</span>
                  <select
                    value={item.paperTypeId}
                    onChange={(e) => {
                      const newPaper = paperCatalog.find((p) => p.id === e.target.value);
                      const newGrammage = newPaper?.grammages[0];
                      handleUpdateLineItem(item.id, {
                        paperTypeId: e.target.value,
                        grammageId: newGrammage?.id ?? '',
                        supplierPriceId: cheapestPrice(newGrammage)?.id ?? '',
                        sheetWidthCm: newPaper?.standardSheetSize.widthCm ?? item.sheetWidthCm,
                        sheetHeightCm: newPaper?.standardSheetSize.heightCm ?? item.sheetHeightCm,
                      });
                    }}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                  >
                    {paperCatalog.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">الجرام</span>
                  <select
                    value={item.grammageId}
                    onChange={(e) => {
                      const newGrammage = paper?.grammages.find((g) => g.id === e.target.value);
                      handleUpdateLineItem(item.id, {
                        grammageId: e.target.value,
                        supplierPriceId: cheapestPrice(newGrammage)?.id ?? '',
                      });
                    }}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                  >
                    {paper?.grammages.map((g) => (
                      <option key={g.id} value={g.id}>
                        {g.gsm} جم
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">المورد</span>
                  <select
                    value={item.supplierPriceId}
                    onChange={(e) => handleUpdateLineItem(item.id, { supplierPriceId: e.target.value })}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                  >
                    {[...(grammage?.prices ?? [])]
                      .sort((a, b) => a.pricePerTonEgp - b.pricePerTonEgp)
                      .map((pr) => (
                        <option key={pr.id} value={pr.id}>
                          {pr.supplierName} — {pr.pricePerTonEgp.toLocaleString('ar-EG')} ج/طن
                        </option>
                      ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 mt-2">
                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">المقاس سم (طول×عرض)</span>
                  <div className="flex gap-1">
                    <input
                      type="number"
                      min={1}
                      value={item.sheetWidthCm}
                      onChange={(e) => handleUpdateLineItem(item.id, { sheetWidthCm: Math.max(1, Number(e.target.value)) })}
                      className="w-1/2 rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs text-center"
                    />
                    <input
                      type="number"
                      min={1}
                      value={item.sheetHeightCm}
                      onChange={(e) => handleUpdateLineItem(item.id, { sheetHeightCm: Math.max(1, Number(e.target.value)) })}
                      className="w-1/2 rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs text-center"
                    />
                  </div>
                </div>

                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">عدد الأفرخ</span>
                  <input
                    type="number"
                    min={1}
                    value={item.sheetsCount}
                    onChange={(e) => handleUpdateLineItem(item.id, { sheetsCount: Math.max(1, Number(e.target.value)) })}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs text-center"
                  />
                </div>
              </div>

              <div className="mt-2 text-[11px] text-slate-500">
                الوزن: {round2(weightKg)} كجم · التكلفة: {round2(costEgp).toLocaleString('ar-EG')} ج
              </div>
            </div>
          );
        })}

        <button
          onClick={handleAddLineItem}
          className="w-full flex items-center justify-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs py-2"
        >
          <Plus className="w-3.5 h-3.5" />
          إضافة بند ورق
        </button>
      </div>

      {/* Manual cost lines */}
      <div className="space-y-2 mb-5">
        <div className="text-xs text-slate-400">مصاريف إضافية (طباعة، تشطيب، شحن...)</div>
        {costLines.map((cl) => (
          <div key={cl.id} className="flex items-center gap-2">
            <input
              type="text"
              placeholder="اسم البند، مثال: طباعة"
              value={cl.label}
              onChange={(e) => handleUpdateCostLine(cl.id, { label: e.target.value })}
              className="flex-1 rounded-lg bg-slate-900 border border-slate-800 px-3 py-2 text-sm"
            />
            <input
              type="number"
              min={0}
              placeholder="المبلغ (ج)"
              value={cl.amountEgp || ''}
              onChange={(e) => handleUpdateCostLine(cl.id, { amountEgp: Number(e.target.value) })}
              className="w-28 rounded-lg bg-slate-900 border border-slate-800 px-3 py-2 text-sm text-center"
            />
            <button onClick={() => handleRemoveCostLine(cl.id)} className="text-red-400 hover:text-red-300">
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
        ))}
        <button
          onClick={handleAddCostLine}
          className="w-full flex items-center justify-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs py-2"
        >
          <Plus className="w-3.5 h-3.5" />
          إضافة بند مصاريف
        </button>
      </div>

      {/* Margin + produced quantity */}
      <div className="grid grid-cols-2 gap-3 mb-5">
        <div>
          <label className="block text-xs text-slate-400 mb-1.5">نسبة الربح %</label>
          <input
            type="number"
            min={0}
            value={marginPercent}
            onChange={(e) => setMarginPercent(Math.max(0, Number(e.target.value)))}
            className="w-full rounded-lg bg-slate-900 border border-slate-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
        </div>
        <div>
          <label className="block text-xs text-slate-400 mb-1.5">عدد القطع النهائي (اختياري، لسعر القطعة)</label>
          <input
            type="number"
            min={0}
            value={producedQuantity}
            onChange={(e) => setProducedQuantity(e.target.value === '' ? '' : Math.max(0, Number(e.target.value)))}
            className="w-full rounded-lg bg-slate-900 border border-slate-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
        </div>
      </div>

      {/* Summary */}
      <div className="rounded-xl bg-slate-900 border border-slate-800 p-4 space-y-1.5">
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-400">إجمالي وزن الورق</span>
          <span>{calc.totalPaperWeightKg.toLocaleString('ar-EG')} كجم</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-400">تكلفة الورق</span>
          <span>{calc.totalPaperCostEgp.toLocaleString('ar-EG')} ج</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-400">مصاريف إضافية</span>
          <span>{calc.totalManualCostsEgp.toLocaleString('ar-EG')} ج</span>
        </div>
        <div className="flex items-center justify-between text-sm border-t border-slate-800 pt-1.5 mt-1.5">
          <span className="text-slate-400">إجمالي التكلفة</span>
          <span className="font-medium">{calc.baseCostEgp.toLocaleString('ar-EG')} ج</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-400">الربح ({marginPercent}%)</span>
          <span>{calc.marginAmountEgp.toLocaleString('ar-EG')} ج</span>
        </div>
        <div className="flex items-center justify-between text-lg border-t border-slate-800 pt-1.5 mt-1.5">
          <span className="text-slate-400 text-sm">السعر النهائي</span>
          <span className="font-bold text-emerald-400">{calc.finalPriceEgp.toLocaleString('ar-EG')} ج</span>
        </div>
        {calc.unitPriceEgp !== null && (
          <div className="flex items-center justify-between text-sm">
            <span className="text-slate-400">سعر القطعة</span>
            <span className="font-medium">{calc.unitPriceEgp.toFixed(2)} ج</span>
          </div>
        )}
      </div>

      <button
        onClick={handleConfirm}
        className="mt-4 w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm py-2.5 font-medium transition-colors"
      >
        أكد وسجل العرض
      </button>
    </div>
  );
}
