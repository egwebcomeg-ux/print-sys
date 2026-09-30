/**
 * QuickBoxPricingCalculator
 * ---------------------------------------------------------------------------
 * حاسبة تسعير العلب ومطابقة الاسطمبات الفورية
 *
 * v3 — adds a live 2D dieline preview (تراسيه العلبة) and a sheet imposition
 * preview (مونتاج الفرخ) as inline SVG, plus an approximate "interlocked
 * imposition" mode (تصميم متداخل) for tuck-style boxes that estimates the
 * paper savings from nesting flaps between rows.
 *
 * IMPORTANT — READ BEFORE USING IN PRODUCTION
 * ---------------------------------------------------------------------------
 * 1) Paper prices come from the server (`papers` prop, أنواع الورق page) and
 *    are PLACEHOLDER EXAMPLES until real supplier quotes are entered there.
 * 2) The box-shape flat-dimension formulas AND the dieline/imposition
 *    previews are schematic approximations, not verified CAD dielines. Each
 *    shape's geometry lives in ONE function in BOX_SHAPE_CALCULATORS below —
 *    when real reference dielines arrive per box type, only that function
 *    needs to change and both previews update automatically.
 * 3) INTERLOCK_HEIGHT_SAVING_RATIO (how much vertical space nesting saves) is
 *    a placeholder estimate (~15%). Real interlocking savings depend on the
 *    exact flap width/notch geometry of each die — confirm with prepress
 *    once you have real dielines, and adjust that one constant.
 * 4) Dies come from the server (`dies` prop, الاسطمبات page).
 * 5) BUSINESS RULE: the profit margin is typed per job (marginPercent) — there
 *    is no fixed markup. Pricing rates come from the `pricingConstants` prop
 *    (ثوابت التسعير page) and fall back to DEFAULT_PRICING below.
 * ---------------------------------------------------------------------------
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box as BoxIcon,
  Check,
  CheckCircle2,
  Copy,
  LayoutGrid,
  Layers,
  MapPin,
  Plus,
  Printer,
  Ruler,
  Scissors,
  RefreshCw,
  Settings2,
  Trash2,
  X,
} from 'lucide-react';

// ============================================================================
// Types
// ============================================================================

export interface DieCutTool {
  id: string;
  code: string; // e.g. "اسطامبة-صيدلي-060" or "D-301810"
  name: string;
  lengthCm: number; // طول
  widthCm: number; // عرض
  depthCm: number; // عمق / ارتفاع العلبة
  closureType: 'reverse_tuck' | 'straight_tuck' | 'auto_bottom' | 'snap_lock' | 'front_lock' | 'glued_corners';
  rackLocation: string; // e.g. "ستاند أ - رف 3"
  upsOnCutSheet: number; // عدد العلب في شابلونة الاسطامبة
  cutFraction: '1/1' | '1/2' | '1/4' | '1/6' | '1/8';
  condition: 'ready' | 'needs_rubber' | 'maintenance';
}

/** One supplier's current price per ton for a given grammage — several can coexist so prices are comparable. */
export interface PaperSupplierPrice {
  id: string;
  supplierName: string;
  pricePerTonEgp: number; // سعر الطن الحالي من هذا المورد — يتغيّر بالسوق، حدّثه من هنا
}

/** One grammage/weight option for a paper type, with prices from one or more suppliers. */
export interface PaperGrammageOption {
  id: string;
  gsm: number; // جرام / متر مربع
  prices: PaperSupplierPrice[];
}

export type PaperCategory =
  | 'duplex_grey_back'
  | 'duplex_white_back'
  | 'bristol_white_back'
  | 'kraft_liner'
  | 'couche'
  | 'triplex_board'
  | 'micro_flute';

/** A paper "type" (e.g. duplex grey back) with the grammages it's stocked in. */
export interface PaperType {
  id: string;
  name: string; // اسم النوع بالعربي، مثال: "دوبلكس ظهر رمادي"
  category: PaperCategory;
  standardSheetSize: { widthCm: number; heightCm: number };
  grammages: PaperGrammageOption[];
}

export type LaminationType = 'none' | 'matte' | 'gloss';
export type ClosureType = DieCutTool['closureType'];

/** The flat/dieline shape family used to compute unfolded dimensions. */
export type BoxShapeId = 'reverse_tuck_end' | 'straight_tuck_end' | 'auto_lock_bottom' | 'pillow_bag' | 'lid_and_base' | 'pizza_box' | 'phone_box' | 'glued_tray_lid';

export type BoxTypeId = 'medicine' | 'candy' | 'cosmetics' | 'food' | 'pizza' | 'phone' | 'oriental' | 'general';

interface BoxTypePreset {
  id: BoxTypeId;
  label: string;
  shape: BoxShapeId;
  suggestedClosure: ClosureType;
  suggestedDims?: { lengthCm: number; widthCm: number; depthCm: number };
  /** Shown in the UI so the team knows this is a placeholder pending real reference specs. */
  note: string;
}

export interface BoxQuote {
  boxType: BoxTypeId;
  shape: BoxShapeId;
  dimensions: { lengthCm: number; widthCm: number; depthCm: number };
  quantity: number;
  paperTypeName: string;
  gsm: number;
  supplierName: string | null;
  pricePerTonEgp: number | null;
  printColors: number;
  lamination: LaminationType;
  isUsingExistingDie: boolean;
  matchedDie: DieCutTool | null;
  unitPriceEgp: number;
  totalPriceEgp: number;
  baseCostEgp: number;
  rawSheetsNeeded: number;
  upsPerRawSheet: number;
  interlocked: boolean;
  // Added for persistence — the server stores FK references, not names.
  paperTypeId: string;
  grammageId: string;
  supplierPriceId: string | null;
  dieId: string | null;
  marginPercent: number;
  marginAmountEgp: number;
  // Flat dieline size (from BOX_SHAPE_CALCULATORS) — the server re-prices from this.
  flatWidthMm: number;
  flatHeightMm: number;
  interlockEnabled: boolean;
  /** Row pitch when rows nest (shape-specific), or null if the shape can't interlock. */
  interlockPitchMm: number | null;
  /** Pieces per box (2 for lid-and-base), and the sheet/cut the plan chose. */
  piecesPerBox: number;
  sheetWidthCm: number;
  sheetHeightCm: number;
  cutFraction: DieCutTool['cutFraction'];
  costBreakdown: {
    paperCost: number;
    platesCost: number;
    pressRunCost: number;
    laminationCost: number;
    dieToolingCost: number;
    dieCuttingRunCost: number;
    gluingCost: number;
  };
}

/** Rate constants the price depends on. All values are PLACEHOLDERS until confirmed. */
export interface PricingConstants {
  spoilageRate: number;
  newDieCostEgp: number;
  plateCostPerColorEgp: number;
  pressRunRatePerColorPer1000SheetsEgp: number;
  laminationRatePerSheetEgp: Record<Exclude<LaminationType, 'none'>, number>;
  dieCutRatePerSheetEgp: number;
  glueFoldRatePerUnitEgp: number;
}

/** A previously priced job, saved so staff can repeat it in one click instead of re-typing every field. */
export interface SavedJobSpec {
  id: string;
  label: string; // مثال: "صيدلية العزبي — علبة دواء 9x5x3"
  boxTypeId: BoxTypeId;
  boxShapeId: BoxShapeId;
  lengthCm: number;
  widthCm: number;
  depthCm: number;
  quantity: number;
  paperTypeId: string;
  grammageId: string;
  supplierPriceId: string | null;
  printColors: number;
  lamination: LaminationType;
  isUsingExistingDie: boolean;
  selectedDieId: string | null;
}

interface QuickBoxPricingCalculatorProps {
  /** Factory die inventory (from the server). */
  dies: DieCutTool[];
  /** Paper catalogue (type → grammages → supplier prices), from the server. */
  papers: PaperType[];
  /** Recently priced jobs the user can reload with one click ("كرر نفس الشغلانة"). */
  pastJobs: SavedJobSpec[];
  /** Rates from the ثوابت التسعير page; missing values fall back to DEFAULT_PRICING. */
  pricingConstants?: Partial<PricingConstants>;
  /** Starting value of the manual margin field — staff type the real margin per job. */
  defaultMarginPercent?: number;
  /** Editing an existing job: preload its spec (and margin) instead of the defaults. */
  initialJob?: SavedJobSpec | null;
  initialMarginPercent?: number | null;
  /** Called when the user confirms the order, with the full computed quote. Wire this to create a Job Ticket / Sales Order. */
  onConfirmOrder?: (quote: BoxQuote) => void;
  className?: string;
}

// ============================================================================
// Data comes from the server (dies, paper catalogue, past jobs). The sample
// constants that used to live here are now database seeders
// (database/seeders/SampleCatalogSeeder.php, SampleJobSeeder.php).
// ============================================================================

/**
 * Box-type presets. Selecting one prefills a shape + suggested dimensions so
 * the team can start pricing immediately; each `note` flags that the shape's
 * flat-dimension formula is a placeholder pending the reference dieline for
 * that specific box type.
 */
const BOX_TYPE_PRESETS: BoxTypePreset[] = [
  { id: 'medicine', label: 'علبة دواء', shape: 'reverse_tuck_end', suggestedClosure: 'reverse_tuck', suggestedDims: { lengthCm: 9, widthCm: 5, depthCm: 3 }, note: 'مقاس تقريبي شائع — اكتب المقاس الفعلي للعلبة' },
  { id: 'candy', label: 'علبة حلويات', shape: 'auto_lock_bottom', suggestedClosure: 'auto_bottom', suggestedDims: { lengthCm: 20, widthCm: 15, depthCm: 8 }, note: 'مقاس تقريبي شائع — هيتظبط بالظبط لما تبعت الرسم المرجعي لعلبة الحلويات' },
  { id: 'cosmetics', label: 'مستحضرات تجميل', shape: 'straight_tuck_end', suggestedClosure: 'straight_tuck', suggestedDims: { lengthCm: 12, widthCm: 8, depthCm: 4 }, note: 'مقاس تقريبي شائع — هيتظبط بالظبط لما تبعت الرسم المرجعي' },
  { id: 'food', label: 'علبة أغذية', shape: 'auto_lock_bottom', suggestedClosure: 'auto_bottom', suggestedDims: { lengthCm: 25, widthCm: 18, depthCm: 10 }, note: 'مقاس تقريبي شائع — هيتظبط بالظبط لما تبعت الرسم المرجعي' },
  { id: 'pizza', label: 'علبة بيتزا (كرتون مايكرو)', shape: 'pizza_box', suggestedClosure: 'front_lock', suggestedDims: { lengthCm: 34, widthCm: 34, depthCm: 4 }, note: 'معايَرة على رسومات 34×34×4 و45×44×4 و35×13×6 — الطول = عرض الفرد، العرض = من الأمام للخلف' },
  { id: 'phone', label: 'علبة تليفون (كرتون مايكرو)', shape: 'phone_box', suggestedClosure: 'front_lock', suggestedDims: { lengthCm: 25, widthCm: 10, depthCm: 6 }, note: 'معايَرة على رسومات 25×10×6 و35×13×6 و20×25×6 و33.5×22.8×5.5 — الطول = عرض القاع، العرض = من الأمام للخلف' },
  { id: 'oriental', label: 'علبة حلويات شرقي (لصق ٦ بونط)', shape: 'glued_tray_lid', suggestedClosure: 'glued_corners', suggestedDims: { lengthCm: 25, widthCm: 18, depthCm: 5 }, note: 'معايَرة على رسومات 25×18×5 و32×27×3 و19×19×3 — الطول = ناحية لسانات اللصق، العرض = من الوش للظهر' },
  { id: 'general', label: 'عام / مقاس مخصص', shape: 'reverse_tuck_end', suggestedClosure: 'reverse_tuck', note: 'مقاس حر تكتبه انت' },
];

const BOX_SHAPE_LABELS: Record<BoxShapeId, string> = {
  reverse_tuck_end: 'قفل عكسي (RTE)',
  straight_tuck_end: 'قفل مستقيم (STE)',
  auto_lock_bottom: 'قاع أوتوماتيك',
  pillow_bag: 'كيس / Pillow',
  lid_and_base: 'قاع وغطاء (قطعتين)',
  pizza_box: 'علبة بيتزا (قفل أمامي)',
  phone_box: 'علبة تليفون (Mailer)',
  glued_tray_lid: 'صينية بغطا — لصق ٦ بونط',
};

// ============================================================================
// Pricing constants — PLACEHOLDERS. Confirm against real factory rates.
// ============================================================================

const GLUE_FLAP_MM = 15; // Project 257 (علبة دواء 68×68×130): 14.5 mm
const BLEED_MM = 2;
// Reverse tuck end — from Project 257 (lip 12, 4 mm row gap) and the 10×10×5 medicine box (lip 14, no gap):
// we take the safer of each so the plan never promises more ups than the dieline allows. PLACEHOLDER.
const TUCK_LIP_MM = 14;
const INTERLOCK_ROW_GAP_MM = 4;
// Lid-and-base tray — from dielines B (قاع 211×134) and C (غطاء 216×144), wall 48, board 1 mm.
const BOARD_THICKNESS_MM = 1;
const TRAY_RETURN_EXTRA_MM = 4; // inner return flap = wall + 4
const LID_CLEARANCE_LENGTH_MM = 5; // lid is longer (216 vs 211) than the base by this
const LID_CLEARANCE_WIDTH_MM = 10; // ...and wider (144 vs 134) by this
const EXACT_MATCH_THRESHOLD_CM = 0.3; // sum of |ΔL| + |ΔW| + |ΔD|
// Rates — PLACEHOLDERS, overridable via the `pricingConstants` prop (ثوابت التسعير page).
const DEFAULT_PRICING: PricingConstants = {
  spoilageRate: 0.03, // +3% raw sheets for press/machine waste
  newDieCostEgp: 650,
  plateCostPerColorEgp: 150,
  pressRunRatePerColorPer1000SheetsEgp: 180,
  laminationRatePerSheetEgp: { matte: 0.35, gloss: 0.3 },
  dieCutRatePerSheetEgp: 0.15,
  glueFoldRatePerUnitEgp: 0.05,
};
// BUSINESS RULE: no fixed PROFIT_MARGIN — margin is typed per job (see marginPercent state).
const GRIPPER_ALLOWANCE_MM = 12;
const SIDE_TRIM_MM = 5;

// Interlocked imposition (تعشيق/تداخل الفلات بين الصفوف لتوفير الورق).
const UNGLUED_SHAPES: BoxShapeId[] = ['pizza_box', 'phone_box'];
const INTERLOCK_CAPABLE_SHAPES: BoxShapeId[] = ['reverse_tuck_end', 'straight_tuck_end', 'auto_lock_bottom'];
const INTERLOCK_HEIGHT_SAVING_RATIO = 0.85; // PLACEHOLDER — real saving depends on flap/notch geometry

const RAW_SHEET_OPTIONS = [
  { name: '70×100', widthCm: 70, heightCm: 100 },
  { name: '88×119', widthCm: 88, heightCm: 119 },
];

const CUT_FRACTION_LABELS: Record<DieCutTool['cutFraction'], string> = { '1/1': 'كامل', '1/2': 'نص', '1/4': 'ربع', '1/6': 'سدس', '1/8': 'تمن' };

const CUT_FRACTION_DENOMINATOR: Record<DieCutTool['cutFraction'], number> = {
  '1/1': 1,
  '1/2': 2,
  '1/4': 4,
  '1/6': 6,
  '1/8': 8,
};

// ============================================================================
// Box-shape flat-dimension registry
// Each function is the ONE place to edit when a real reference dieline for
// that shape arrives — the dieline preview and imposition preview both read
// from its output, so nothing else needs to change.
// ============================================================================

/** Full geometry description of a box's unfolded (flat) dieline. */
interface FlatDims {
  flatWidthMm: number;
  flatHeightMm: number;
  /** Top and bottom flap-zone heights (tuck flaps, lock flaps, seals...). */
  topFlapMm: number;
  bottomFlapMm: number;
  /** Vertical panel widths, left→right, excluding bleed — sums to flatWidthMm - 2*BLEED_MM. */
  panelWidthsMm: number[];
  /** Optional labels for each panel width, same length/order as panelWidthsMm. */
  panelLabels: string[];
  /** Row pitch when rows nest (tuck-style shapes); undefined = use the generic ratio. */
  interlockPitchMm?: number;
  /** Multi-piece boxes (lid + base): flat dims above are the largest piece. */
  piecesPerBox?: number;
  pieces?: { label: string; widthMm: number; heightMm: number }[];
}

/**
 * Calibrated on Project 257 (68×68×130 → flat 286.5×290, rows nest at 214 mm pitch):
 * the tuck flap is the panel WIDTH plus a lip, not a fraction of the depth.
 */
function calcReverseTuckEnd(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  const tuckFlapMm = widthMm + TUCK_LIP_MM;
  const panelWidthsMm = [GLUE_FLAP_MM, widthMm, lengthMm, widthMm, lengthMm];
  const bodyWidthMm = panelWidthsMm.reduce((a, b) => a + b, 0);
  const flatHeightMm = depthMm + 2 * tuckFlapMm + 2 * BLEED_MM;
  return {
    flatWidthMm: bodyWidthMm + 2 * BLEED_MM,
    flatHeightMm,
    topFlapMm: tuckFlapMm,
    bottomFlapMm: tuckFlapMm,
    panelWidthsMm,
    panelLabels: ['لسان لصق', 'جانب', 'أمام', 'جانب', 'خلف'],
    // Alternating rows nest the tuck flap of one row beside the dust flaps of the next.
    interlockPitchMm: flatHeightMm - tuckFlapMm + INTERLOCK_ROW_GAP_MM,
  };
}

/**
 * Two-piece tray box (قاع وغطاء), from dielines B (base 211×134×48 → 307×336)
 * and C (lid 216×144×48 → 312×346): the long side runs across the flat with a
 * plain wall each side; the short side gets double walls with an inner return.
 * Flat = (L + 2·wall) × (W + 2·(2·wall + board + 4)); the lid is +5 L / +10 W.
 * `depthMm` is the wall height. Returned dims are the lid (the larger piece).
 */
function calcLidAndBase(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  const endZoneMm = 2 * depthMm + BOARD_THICKNESS_MM + TRAY_RETURN_EXTRA_MM;
  const piece = (l: number, w: number) => ({ widthMm: l + 2 * depthMm + 2 * BLEED_MM, heightMm: w + 2 * endZoneMm + 2 * BLEED_MM });
  const base = piece(lengthMm, widthMm);
  const lid = piece(lengthMm + LID_CLEARANCE_LENGTH_MM, widthMm + LID_CLEARANCE_WIDTH_MM);
  return {
    flatWidthMm: lid.widthMm,
    flatHeightMm: lid.heightMm,
    topFlapMm: endZoneMm,
    bottomFlapMm: endZoneMm,
    panelWidthsMm: [depthMm, lengthMm + LID_CLEARANCE_LENGTH_MM, depthMm],
    panelLabels: ['جنب', 'غطاء', 'جنب'],
    piecesPerBox: 2,
    pieces: [
      { label: 'قاع', ...base },
      { label: 'غطاء', ...lid },
    ],
  };
}

// Pizza box (roll-end front lock, micro flute) — from dielines 34×34×4 → 420×840.5,
// 45×44×4 → 530×1040.5 and 35×13×6 → 470×475.5 (L across the flat, W front→back).
// Bottom→top: lock tabs, inner front wall, double crease, front wall, base, back
// wall, lid, lid front flap. PLACEHOLDER constants below — confirm with the die maker.
const PIZZA_LOCK_TAB_MM = 5;
const PIZZA_ROLL_CREASE_MM = 4; // double crease where the front wall rolls over (board thickness)
const PIZZA_BACK_WALL_MAX_MM = 40; // back hinge wall was 40 mm in all three references
const PIZZA_LID_SHORTER_MM = 3; // lid = base depth − 3

function calcPizzaBox(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  const backWallMm = Math.min(depthMm, PIZZA_BACK_WALL_MAX_MM);
  const lidFlapMm = Math.round(depthMm * 0.75 + 5); // 40 → 35, 60 → 50
  const belowBaseMm = PIZZA_LOCK_TAB_MM + (depthMm - 0.5) + PIZZA_ROLL_CREASE_MM + depthMm;
  const aboveBaseMm = backWallMm + (widthMm - PIZZA_LID_SHORTER_MM) + lidFlapMm;
  return {
    flatWidthMm: lengthMm + 2 * depthMm + 2 * BLEED_MM,
    flatHeightMm: belowBaseMm + widthMm + aboveBaseMm + 2 * BLEED_MM,
    topFlapMm: aboveBaseMm,
    bottomFlapMm: belowBaseMm,
    panelWidthsMm: [depthMm, lengthMm, depthMm],
    panelLabels: ['جنب', 'قاع / غطاء', 'جنب'],
  };
}

// Phone box (mailer with double side walls, micro flute) — from dielines 25×10×6 → 511×381,
// 35×13×6 → 613×441, 20×25×6 → 463×681 and 33.5×22.8×5.5 → 578×622 (L across, W front→back).
// Across: lock tab, inner side wall (D − 0.5), roll crease, side wall D, base L, and mirrored.
// Up: front wall (D − 0.5), base W, back wall (D − 0.5), lid (W + 1), lid front flap (D − 2) + creases.
const PHONE_LOCK_TAB_MM = 5;
const PHONE_ROLL_CREASE_MM = 7; // 7 mm in three references, 6 in one — the safer value. PLACEHOLDER.

function calcPhoneBox(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  const sideZoneMm = PHONE_LOCK_TAB_MM + (depthMm - 0.5) + PHONE_ROLL_CREASE_MM;
  const panelWidthsMm = [sideZoneMm, depthMm, lengthMm, depthMm, sideZoneMm];
  const frontWallMm = depthMm - 0.5;
  const aboveBaseMm = depthMm - 0.5 + 1 + (widthMm + 1) + 2 + (depthMm - 2);
  return {
    flatWidthMm: panelWidthsMm.reduce((a, b) => a + b, 0) + 2 * BLEED_MM,
    flatHeightMm: frontWallMm + widthMm + aboveBaseMm + 2 * BLEED_MM,
    topFlapMm: aboveBaseMm,
    bottomFlapMm: frontWallMm,
    panelWidthsMm,
    panelLabels: ['جنب داخلي', 'جنب', 'قاع / غطاء', 'جنب', 'جنب داخلي'],
  };
}

// Tray with hinged lid, 6 glue points (علب شرقي لصق ٦ بونط) — from dielines 25×18×5 → 511×350,
// 32×27×3 → 631×382 and 19×19×3 → 471×250. Across: lid front wall, lid (W + 0.5), hinge 0.5,
// back wall, base W, front wall → 3D + 2W + 1. Along: L + two walls; one reference makes the
// lid 2 mm longer, so we keep the +2 (safer). Glue ears sit on the base corners and lid front.
const TRAY_LID_EXTRA_LENGTH_MM = 2; // PLACEHOLDER — 0 in two references, 2 in one

function calcGluedTrayLid(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  const panelWidthsMm = [depthMm, widthMm + 1, depthMm, widthMm, depthMm];
  const wallZoneMm = depthMm + TRAY_LID_EXTRA_LENGTH_MM / 2;
  return {
    flatWidthMm: panelWidthsMm.reduce((a, b) => a + b, 0) + 2 * BLEED_MM,
    flatHeightMm: lengthMm + 2 * wallZoneMm + 2 * BLEED_MM,
    topFlapMm: wallZoneMm,
    bottomFlapMm: wallZoneMm,
    panelWidthsMm,
    panelLabels: ['وش الغطا', 'غطا', 'ظهر', 'قاع', 'وش'],
  };
}

function calcStraightTuckEnd(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  // Placeholder: straight-tuck dust flaps are usually shorter than reverse-tuck.
  const tuckFlapMm = Math.max(12, Math.round(depthMm * 0.5));
  const panelWidthsMm = [GLUE_FLAP_MM, widthMm, lengthMm, widthMm, lengthMm];
  const bodyWidthMm = panelWidthsMm.reduce((a, b) => a + b, 0);
  return {
    flatWidthMm: bodyWidthMm + 2 * BLEED_MM,
    flatHeightMm: depthMm + 2 * tuckFlapMm + 2 * BLEED_MM,
    topFlapMm: tuckFlapMm,
    bottomFlapMm: tuckFlapMm,
    panelWidthsMm,
    panelLabels: ['لسان لصق', 'جانب', 'أمام', 'جانب', 'خلف'],
  };
}

function calcAutoLockBottom(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  // Placeholder: simple top tuck + a larger locking bottom flap allowance.
  const topTuckFlapMm = Math.max(15, Math.round(depthMm * 0.6));
  const bottomLockFlapMm = Math.round(widthMm * 0.4);
  const panelWidthsMm = [GLUE_FLAP_MM, widthMm, lengthMm, widthMm, lengthMm];
  const bodyWidthMm = panelWidthsMm.reduce((a, b) => a + b, 0);
  return {
    flatWidthMm: bodyWidthMm + 2 * BLEED_MM,
    flatHeightMm: depthMm + topTuckFlapMm + bottomLockFlapMm + 2 * BLEED_MM,
    topFlapMm: topTuckFlapMm,
    bottomFlapMm: bottomLockFlapMm,
    panelWidthsMm,
    panelLabels: ['لسان لصق', 'جانب', 'أمام', 'جانب', 'خلف'],
  };
}

function calcPillowBag(lengthMm: number, widthMm: number, depthMm: number): FlatDims {
  // Placeholder: gusseted bag with a side seal overlap and top/bottom seals.
  const sealOverlapMm = 20;
  const sealTopBottomMm = 15;
  const panelWidthsMm = [depthMm, widthMm, depthMm, sealOverlapMm];
  return {
    flatWidthMm: panelWidthsMm.reduce((a, b) => a + b, 0),
    flatHeightMm: lengthMm + 2 * sealTopBottomMm,
    topFlapMm: sealTopBottomMm,
    bottomFlapMm: sealTopBottomMm,
    panelWidthsMm,
    panelLabels: ['جانب مطوي', 'أمام', 'جانب مطوي', 'لحام تراكب'],
  };
}

const BOX_SHAPE_CALCULATORS: Record<BoxShapeId, (lengthMm: number, widthMm: number, depthMm: number) => FlatDims> = {
  reverse_tuck_end: calcReverseTuckEnd,
  straight_tuck_end: calcStraightTuckEnd,
  auto_lock_bottom: calcAutoLockBottom,
  pillow_bag: calcPillowBag,
  lid_and_base: calcLidAndBase,
  pizza_box: calcPizzaBox,
  phone_box: calcPhoneBox,
  glued_tray_lid: calcGluedTrayLid,
};

// ============================================================================
// Helpers
// ============================================================================

function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

/** Cost of one raw sheet from its area, the grammage (g/m²), and a chosen supplier's price per ton. */
function paperCostPerSheetEgp(sheet: { widthCm: number; heightCm: number }, grammage: PaperGrammageOption, pricePerTonEgp: number): number {
  const areaM2 = (sheet.widthCm / 100) * (sheet.heightCm / 100);
  const sheetWeightKg = (areaM2 * grammage.gsm) / 1000;
  const pricePerKg = pricePerTonEgp / 1000;
  return sheetWeightKg * pricePerKg;
}

/** The cheapest supplier price for a grammage, or null if it has none registered yet. */
function cheapestPrice(grammage: PaperGrammageOption | undefined): PaperSupplierPrice | null {
  if (!grammage || grammage.prices.length === 0) return null;
  return [...grammage.prices].sort((a, b) => a.pricePerTonEgp - b.pricePerTonEgp)[0];
}

function getCutSheetDimsMm(rawWidthCm: number, rawHeightCm: number, fraction: DieCutTool['cutFraction']) {
  const rawWidthMm = rawWidthCm * 10;
  const rawHeightMm = rawHeightCm * 10;
  switch (fraction) {
    case '1/1':
      return { widthMm: rawWidthMm, heightMm: rawHeightMm };
    case '1/2':
      return { widthMm: rawWidthMm, heightMm: rawHeightMm / 2 };
    case '1/4':
      return { widthMm: rawWidthMm / 2, heightMm: rawHeightMm / 2 };
    case '1/6':
      return { widthMm: rawWidthMm / 3, heightMm: rawHeightMm / 2 };
    case '1/8':
      return { widthMm: rawWidthMm / 4, heightMm: rawHeightMm / 2 };
  }
}

interface UpsGrid {
  cols: number;
  rows: number;
  ups: number;
  rotated: boolean;
  unitWidthMm: number;
  unitHeightMm: number;
}

/** Simple grid packing (no interlocking), trying both orientations and keeping the better one. */
function computeUpsGrid(usableWidthMm: number, usableHeightMm: number, flatWidthMm: number, flatHeightMm: number): UpsGrid {
  const normalCols = Math.floor(usableWidthMm / flatWidthMm);
  const normalRows = Math.floor(usableHeightMm / flatHeightMm);
  const normalUps = normalCols * normalRows;

  const rotatedCols = Math.floor(usableWidthMm / flatHeightMm);
  const rotatedRows = Math.floor(usableHeightMm / flatWidthMm);
  const rotatedUps = rotatedCols * rotatedRows;

  if (rotatedUps > normalUps) {
    return { cols: rotatedCols, rows: rotatedRows, ups: rotatedUps, rotated: true, unitWidthMm: flatHeightMm, unitHeightMm: flatWidthMm };
  }
  return { cols: normalCols, rows: normalRows, ups: normalUps, rotated: false, unitWidthMm: flatWidthMm, unitHeightMm: flatHeightMm };
}

let localIdCounter = 0;
function nextLocalId(prefix: string): string {
  localIdCounter += 1;
  return `${prefix}-${Date.now()}-${localIdCounter}`;
}

// ============================================================================
// Dieline preview (2D تراسيه) — schematic SVG built directly from FlatDims,
// so it always matches whatever BOX_SHAPE_CALCULATORS currently computes.
// ============================================================================

function DielinePreview({ dims }: { dims: FlatDims }) {
  const { flatWidthMm, flatHeightMm, topFlapMm, bottomFlapMm, panelWidthsMm, panelLabels } = dims;
  const strokeW = Math.max(flatWidthMm, flatHeightMm) / 220;
  const fontSize = Math.max(flatWidthMm, flatHeightMm) / 24;

  let x = BLEED_MM;
  const panelBoundaries: number[] = [x];
  for (const w of panelWidthsMm) {
    x += w;
    panelBoundaries.push(x);
  }

  const topFoldY = BLEED_MM + topFlapMm;
  const bottomFoldY = flatHeightMm - BLEED_MM - bottomFlapMm;

  return (
    <svg viewBox={`0 0 ${flatWidthMm} ${flatHeightMm}`} className="w-full h-auto" style={{ maxHeight: 240 }}>
      {/* cut line (outer boundary) */}
      <rect x={0} y={0} width={flatWidthMm} height={flatHeightMm} fill="#0f172a" stroke="#38bdf8" strokeWidth={strokeW} />

      {/* vertical fold lines between panels */}
      {panelBoundaries.slice(1, -1).map((bx, i) => (
        <line key={`v-${i}`} x1={bx} y1={BLEED_MM} x2={bx} y2={flatHeightMm - BLEED_MM} stroke="#64748b" strokeWidth={strokeW * 0.6} strokeDasharray={`${strokeW * 2},${strokeW * 2}`} />
      ))}

      {/* horizontal fold lines: top flap zone / body / bottom flap zone */}
      <line x1={BLEED_MM} y1={topFoldY} x2={flatWidthMm - BLEED_MM} y2={topFoldY} stroke="#64748b" strokeWidth={strokeW * 0.6} strokeDasharray={`${strokeW * 2},${strokeW * 2}`} />
      <line x1={BLEED_MM} y1={bottomFoldY} x2={flatWidthMm - BLEED_MM} y2={bottomFoldY} stroke="#64748b" strokeWidth={strokeW * 0.6} strokeDasharray={`${strokeW * 2},${strokeW * 2}`} />

      {/* body panel tint */}
      <rect x={BLEED_MM} y={topFoldY} width={flatWidthMm - 2 * BLEED_MM} height={bottomFoldY - topFoldY} fill="rgba(56,189,248,0.08)" />

      {/* panel labels (body row only, to avoid clutter) */}
      {panelWidthsMm.map((w, i) => {
        const cx = panelBoundaries[i] + w / 2;
        const cy = (topFoldY + bottomFoldY) / 2;
        return (
          <text key={`lbl-${i}`} x={cx} y={cy} fontSize={fontSize} fill="#94a3b8" textAnchor="middle" dominantBaseline="middle">
            {panelLabels[i] ?? ''}
          </text>
        );
      })}

      {/* overall dimension captions */}
      <text x={flatWidthMm / 2} y={fontSize * 1.1} fontSize={fontSize} fill="#e2e8f0" textAnchor="middle">
        {round2(flatWidthMm / 10)} سم
      </text>
      <text x={fontSize * 0.6} y={flatHeightMm / 2} fontSize={fontSize} fill="#e2e8f0" textAnchor="middle" transform={`rotate(-90 ${fontSize * 0.6} ${flatHeightMm / 2})`}>
        {round2(flatHeightMm / 10)} سم
      </text>
    </svg>
  );
}

// ============================================================================
// Imposition preview (مونتاج الفرخ) — draws the cut sheet, its gripper/trim
// margins, and the flats grid; alternates row tint when interlocking is on
// to represent the flipped/nested orientation.
// ============================================================================

function ImpositionPreview({
  sheetWidthMm,
  sheetHeightMm,
  marginTopMm,
  marginSideMm,
  cols,
  rows,
  unitWidthMm,
  unitHeightMm,
  rowPitchMm,
  interlocked,
}: {
  sheetWidthMm: number;
  sheetHeightMm: number;
  marginTopMm: number;
  marginSideMm: number;
  cols: number;
  rows: number;
  unitWidthMm: number;
  unitHeightMm: number;
  rowPitchMm: number;
  interlocked: boolean;
}) {
  const strokeW = Math.max(sheetWidthMm, sheetHeightMm) / 260;

  return (
    <svg viewBox={`0 0 ${sheetWidthMm} ${sheetHeightMm}`} className="w-full h-auto" style={{ maxHeight: 240 }}>
      <rect x={0} y={0} width={sheetWidthMm} height={sheetHeightMm} fill="#0f172a" stroke="#475569" strokeWidth={strokeW} />

      {/* gripper edge + side trims */}
      <rect x={0} y={0} width={sheetWidthMm} height={marginTopMm} fill="#1e293b" opacity={0.7} />
      <rect x={0} y={0} width={marginSideMm} height={sheetHeightMm} fill="#1e293b" opacity={0.7} />
      <rect x={sheetWidthMm - marginSideMm} y={0} width={marginSideMm} height={sheetHeightMm} fill="#1e293b" opacity={0.7} />

      {Array.from({ length: Math.max(0, rows) }).map((_, r) =>
        Array.from({ length: Math.max(0, cols) }).map((_, c) => {
          const x = marginSideMm + c * unitWidthMm;
          const y = marginTopMm + r * rowPitchMm;
          const flipped = interlocked && r % 2 === 1;
          return (
            <rect
              key={`${r}-${c}`}
              x={x}
              y={y}
              width={unitWidthMm}
              height={unitHeightMm}
              fill={flipped ? 'rgba(245,158,11,0.18)' : 'rgba(56,189,248,0.18)'}
              stroke={flipped ? '#f59e0b' : '#38bdf8'}
              strokeWidth={strokeW * 0.7}
            />
          );
        })
      )}
    </svg>
  );
}

// ============================================================================
// Component
// ============================================================================

export default function QuickBoxPricingCalculator({
  dies,
  papers,
  pastJobs,
  pricingConstants,
  defaultMarginPercent = 20,
  initialJob = null,
  initialMarginPercent = null,
  onConfirmOrder,
  className = '',
}: QuickBoxPricingCalculatorProps) {
  const pricing = useMemo<PricingConstants>(
    () => ({
      ...DEFAULT_PRICING,
      ...pricingConstants,
      laminationRatePerSheetEgp: {
        ...DEFAULT_PRICING.laminationRatePerSheetEgp,
        ...pricingConstants?.laminationRatePerSheetEgp,
      },
    }),
    [pricingConstants]
  );

  // Paper catalogue is kept in local state so the "إدارة الورق" panel can add
  // types/grammages/supplier prices live.
  const [paperCatalog, setPaperCatalog] = useState<PaperType[]>(papers);

  const [boxTypeId, setBoxTypeId] = useState<BoxTypeId>('medicine');
  const [boxShapeId, setBoxShapeId] = useState<BoxShapeId>('reverse_tuck_end');
  const [boxLengthCm, setBoxLengthCm] = useState(9);
  const [boxWidthCm, setBoxWidthCm] = useState(5);
  const [boxDepthCm, setBoxDepthCm] = useState(3);
  const [quantity, setQuantity] = useState(3000);
  const [printColors, setPrintColors] = useState(2);
  const [paperTypeId, setPaperTypeId] = useState(paperCatalog[0]?.id ?? '');
  const [grammageId, setGrammageId] = useState(paperCatalog[0]?.grammages[0]?.id ?? '');
  const [supplierPriceId, setSupplierPriceId] = useState<string | null>(
    cheapestPrice(paperCatalog[0]?.grammages[0])?.id ?? null
  );
  const [lamination, setLamination] = useState<LaminationType>('matte');
  const [isUsingExistingDie, setIsUsingExistingDie] = useState(true);
  const [selectedDieId, setSelectedDieId] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [showPaperManager, setShowPaperManager] = useState(false);
  const [showPastJobs, setShowPastJobs] = useState(false);
  const [interlockEnabled, setInterlockEnabled] = useState(true);
  // Typed per job by staff — never a fixed markup (business rule).
  const [marginPercent, setMarginPercent] = useState(defaultMarginPercent);

  // --- Paper manager form state ------------------------------------------
  const [newPaperName, setNewPaperName] = useState('');
  const [newPaperCategory, setNewPaperCategory] = useState<PaperCategory>('duplex_grey_back');
  const [newGrammageGsm, setNewGrammageGsm] = useState(300);
  const [newSupplierName, setNewSupplierName] = useState('');
  const [newGrammagePrice, setNewGrammagePrice] = useState(15000);

  const selectedPaperType = useMemo(
    () => paperCatalog.find((p) => p.id === paperTypeId) ?? paperCatalog[0],
    [paperCatalog, paperTypeId]
  );
  const selectedGrammage = useMemo(
    () => selectedPaperType?.grammages.find((g) => g.id === grammageId) ?? selectedPaperType?.grammages[0],
    [selectedPaperType, grammageId]
  );
  const selectedSupplierPrice = useMemo(
    () => selectedGrammage?.prices.find((p) => p.id === supplierPriceId) ?? cheapestPrice(selectedGrammage),
    [selectedGrammage, supplierPriceId]
  );
  const sortedSupplierPrices = useMemo(
    () => (selectedGrammage ? [...selectedGrammage.prices].sort((a, b) => a.pricePerTonEgp - b.pricePerTonEgp) : []),
    [selectedGrammage]
  );

  // --- Repeat-job (كرر نفس الشغلانة) ---------------------------------------
  const handleRepeatJob = useCallback((job: SavedJobSpec) => {
    setBoxTypeId(job.boxTypeId);
    setBoxShapeId(job.boxShapeId);
    setBoxLengthCm(job.lengthCm);
    setBoxWidthCm(job.widthCm);
    setBoxDepthCm(job.depthCm);
    setQuantity(job.quantity);
    setPaperTypeId(job.paperTypeId);
    setGrammageId(job.grammageId);
    setSupplierPriceId(job.supplierPriceId);
    setPrintColors(job.printColors);
    setLamination(job.lamination);
    setIsUsingExistingDie(job.isUsingExistingDie);
    setSelectedDieId(job.selectedDieId);
    setShowPastJobs(false);
  }, []);

  // Editing: load the saved job once, exactly like "كرر نفس الشغلانة", plus its margin.
  useEffect(() => {
    if (initialJob) {
      handleRepeatJob(initialJob);
      if (initialMarginPercent !== null) setMarginPercent(initialMarginPercent);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- mount only
  }, []);

  // --- Box-type preset selection -------------------------------------------
  const handleSelectBoxType = useCallback((preset: BoxTypePreset) => {
    setBoxTypeId(preset.id);
    setBoxShapeId(preset.shape);
    if (preset.suggestedDims) {
      setBoxLengthCm(preset.suggestedDims.lengthCm);
      setBoxWidthCm(preset.suggestedDims.widthCm);
      setBoxDepthCm(preset.suggestedDims.depthCm);
    }
    setIsUsingExistingDie(false);
    setSelectedDieId(null);
  }, []);

  const activeBoxTypePreset = useMemo(() => BOX_TYPE_PRESETS.find((p) => p.id === boxTypeId) ?? BOX_TYPE_PRESETS[0], [boxTypeId]);

  // --- Paper manager actions ------------------------------------------------
  const handleAddGrammageToSelectedPaper = useCallback(() => {
    if (!selectedPaperType) return;
    const firstPrice: PaperSupplierPrice = {
      id: nextLocalId('price'),
      supplierName: newSupplierName.trim() || 'مورد جديد',
      pricePerTonEgp: newGrammagePrice,
    };
    const newGrammage: PaperGrammageOption = { id: nextLocalId('gsm'), gsm: newGrammageGsm, prices: [firstPrice] };
    setPaperCatalog((prev) =>
      prev.map((p) => (p.id === selectedPaperType.id ? { ...p, grammages: [...p.grammages, newGrammage] } : p))
    );
    setGrammageId(newGrammage.id);
    setSupplierPriceId(firstPrice.id);
  }, [selectedPaperType, newGrammageGsm, newSupplierName, newGrammagePrice]);

  const handleAddNewPaperType = useCallback(() => {
    if (!newPaperName.trim()) return;
    const firstPrice: PaperSupplierPrice = {
      id: nextLocalId('price'),
      supplierName: newSupplierName.trim() || 'مورد جديد',
      pricePerTonEgp: newGrammagePrice,
    };
    const newType: PaperType = {
      id: nextLocalId('paper'),
      name: newPaperName.trim(),
      category: newPaperCategory,
      standardSheetSize: { widthCm: 70, heightCm: 100 },
      grammages: [{ id: nextLocalId('gsm'), gsm: newGrammageGsm, prices: [firstPrice] }],
    };
    setPaperCatalog((prev) => [...prev, newType]);
    setPaperTypeId(newType.id);
    setGrammageId(newType.grammages[0].id);
    setSupplierPriceId(firstPrice.id);
    setNewPaperName('');
  }, [newPaperName, newPaperCategory, newGrammageGsm, newSupplierName, newGrammagePrice]);

  const handleRemoveGrammage = useCallback((paperId: string, gsmId: string) => {
    setPaperCatalog((prev) =>
      prev.map((p) => (p.id === paperId ? { ...p, grammages: p.grammages.filter((g) => g.id !== gsmId) } : p))
    );
  }, []);

  /** Adds a new supplier price to the currently selected grammage. */
  const handleAddSupplierPriceToSelectedGrammage = useCallback(() => {
    if (!selectedPaperType || !selectedGrammage) return;
    if (!newSupplierName.trim()) return;
    const newPrice: PaperSupplierPrice = { id: nextLocalId('price'), supplierName: newSupplierName.trim(), pricePerTonEgp: newGrammagePrice };
    setPaperCatalog((prev) =>
      prev.map((p) =>
        p.id === selectedPaperType.id
          ? {
              ...p,
              grammages: p.grammages.map((g) => (g.id === selectedGrammage.id ? { ...g, prices: [...g.prices, newPrice] } : g)),
            }
          : p
      )
    );
    setSupplierPriceId(newPrice.id);
    setNewSupplierName('');
  }, [selectedPaperType, selectedGrammage, newSupplierName, newGrammagePrice]);

  const handleRemoveSupplierPrice = useCallback((paperId: string, gsmId: string, priceId: string) => {
    setPaperCatalog((prev) =>
      prev.map((p) =>
        p.id === paperId
          ? { ...p, grammages: p.grammages.map((g) => (g.id === gsmId ? { ...g, prices: g.prices.filter((pr) => pr.id !== priceId) } : g)) }
          : p
      )
    );
  }, []);

  // --- Smart die matching ---------------------------------------------------
  const closestDie = useMemo(() => {
    const readyDies = dies.filter((d) => d.condition !== 'maintenance');
    const pool = readyDies.length > 0 ? readyDies : dies;
    if (pool.length === 0) return null;
    let best = pool[0];
    let bestDiff = Infinity;
    for (const d of pool) {
      const diff = Math.abs(d.lengthCm - boxLengthCm) + Math.abs(d.widthCm - boxWidthCm) + Math.abs(d.depthCm - boxDepthCm);
      if (diff < bestDiff) {
        bestDiff = diff;
        best = d;
      }
    }
    return { die: best, diffCm: bestDiff, isExactMatch: bestDiff <= EXACT_MATCH_THRESHOLD_CM };
  }, [dies, boxLengthCm, boxWidthCm, boxDepthCm]);

  const selectedDie = useMemo(() => dies.find((d) => d.id === selectedDieId) ?? null, [dies, selectedDieId]);

  // --- Die action buttons ----------------------------------------------------
  const handleUseExistingDieSize = useCallback(() => {
    if (!closestDie) return;
    setBoxLengthCm(closestDie.die.lengthCm);
    setBoxWidthCm(closestDie.die.widthCm);
    setBoxDepthCm(closestDie.die.depthCm);
    setSelectedDieId(closestDie.die.id);
    setIsUsingExistingDie(true);
  }, [closestDie]);

  const handleRequestNewDie = useCallback(() => {
    setIsUsingExistingDie(false);
    setSelectedDieId(null);
  }, []);

  const handlePriceWithoutNewDie = useCallback(() => {
    if (!closestDie) return;
    setSelectedDieId(closestDie.die.id);
    setIsUsingExistingDie(true);
  }, [closestDie]);

  // --- Flat dieline geometry (feeds both the preview and the pricing calc) --
  const flatDims = useMemo(
    () => BOX_SHAPE_CALCULATORS[boxShapeId](boxLengthCm * 10, boxWidthCm * 10, boxDepthCm * 10),
    [boxShapeId, boxLengthCm, boxWidthCm, boxDepthCm]
  );

  const shapeCapableOfInterlock = INTERLOCK_CAPABLE_SHAPES.includes(boxShapeId);

  // --- Core calculation --------------------------------------------------
  const calc = useMemo(() => {
    const { flatWidthMm, flatHeightMm } = flatDims;
    const piecesPerBox = flatDims.piecesPerBox ?? 1;
    const interlockPitch = flatDims.interlockPitchMm ?? flatHeightMm * INTERLOCK_HEIGHT_SAVING_RATIO;
    const interlockCandidate = interlockEnabled && shapeCapableOfInterlock && !(isUsingExistingDie && selectedDie);

    // Ups on one cut sheet: plain grid vs nested rows (nesting only when it wins).
    // Multi-piece boxes need whole sets (lid + base) per sheet, so odd ups are rounded down.
    const evaluateCut = (sheet: { widthCm: number; heightCm: number }, fr: DieCutTool['cutFraction']) => {
      const cut = getCutSheetDimsMm(sheet.widthCm, sheet.heightCm, fr);
      const usableW = cut.widthMm - 2 * SIDE_TRIM_MM;
      const usableH = cut.heightMm - GRIPPER_ALLOWANCE_MM;
      const grid = computeUpsGrid(usableW, usableH, flatWidthMm, flatHeightMm);
      const nestCols = Math.max(0, Math.floor(usableW / flatWidthMm));
      const nestRows = usableH >= flatHeightMm ? Math.floor((usableH - flatHeightMm) / interlockPitch) + 1 : 0;
      const nested = interlockCandidate && nestCols * nestRows > grid.ups;
      const rawUps = nested ? nestCols * nestRows : grid.ups;
      const ups = Math.floor(rawUps / piecesPerBox) * piecesPerBox;
      return { sheet, fraction: fr, cut, grid, nested, nestCols, nestRows, ups };
    };

    // Sheet/cut plan: a die fixes both; otherwise pick the cheapest standard sheet + cut
    // (paper cost for the whole run), preferring fewer, larger cuts on ties.
    const paperSheet = selectedPaperType?.standardSheetSize ?? RAW_SHEET_OPTIONS[0];
    const pricePerTon = selectedSupplierPrice?.pricePerTonEgp ?? 0;
    const spoilage = 1 + pricing.spoilageRate;
    // Machine work is charged per cut sheet fed, so it belongs in the plan comparison too.
    const machineCostPerCutSheet =
      (printColors > 0 ? printColors * pricing.pressRunRatePerColorPer1000SheetsEgp / 1000 : 0) +
      (lamination !== 'none' ? pricing.laminationRatePerSheetEgp[lamination] : 0) +
      pricing.dieCutRatePerSheetEgp;
    let plan: ReturnType<typeof evaluateCut>;
    if (isUsingExistingDie && selectedDie) {
      plan = evaluateCut(paperSheet, selectedDie.cutFraction);
    } else {
      const sheets = [paperSheet, ...RAW_SHEET_OPTIONS.filter((o) => o.widthCm !== paperSheet.widthCm || o.heightCm !== paperSheet.heightCm)];
      const fractions: DieCutTool['cutFraction'][] = ['1/1', '1/2', '1/4'];
      const candidates = sheets
        .flatMap((sh) => fractions.map((fr) => evaluateCut(sh, fr)))
        .filter((c) => c.ups > 0)
        .map((c) => {
          const perRaw = c.ups * CUT_FRACTION_DENOMINATOR[c.fraction];
          const sheetsNeeded = Math.ceil(((quantity * piecesPerBox) / perRaw) * spoilage);
          const cost =
            sheetsNeeded * (selectedGrammage ? paperCostPerSheetEgp(c.sheet, selectedGrammage, pricePerTon) : 0) +
            sheetsNeeded * CUT_FRACTION_DENOMINATOR[c.fraction] * machineCostPerCutSheet;
          return { c, cost, sheetsNeeded };
        })
        .sort((a, b) => a.cost - b.cost || a.sheetsNeeded - b.sheetsNeeded || CUT_FRACTION_DENOMINATOR[a.c.fraction] - CUT_FRACTION_DENOMINATOR[b.c.fraction]);
      plan = candidates[0]?.c ?? evaluateCut(paperSheet, '1/2');
    }

    const rawSheet = plan.sheet;
    const fraction = plan.fraction;
    const cutSheet = plan.cut;
    const plainGrid = plan.grid;
    const useInterlock = plan.nested;
    const interlockCols = plan.nestCols;
    const interlockRows = plan.nestRows;

    let cols: number;
    let rows: number;
    let unitWidthMm: number;
    let unitHeightMm: number;
    let rowPitchMm: number;

    if (isUsingExistingDie && selectedDie) {
      // Trust the die's known, real-world ups count over any estimate.
      cols = selectedDie.upsOnCutSheet;
      rows = 1;
      unitWidthMm = flatWidthMm;
      unitHeightMm = flatHeightMm;
      rowPitchMm = flatHeightMm;
    } else if (useInterlock) {
      cols = interlockCols;
      rows = interlockRows;
      unitWidthMm = flatWidthMm;
      unitHeightMm = flatHeightMm;
      rowPitchMm = interlockPitch;
    } else {
      const grid = plainGrid;
      cols = grid.cols;
      rows = grid.rows;
      unitWidthMm = grid.unitWidthMm;
      unitHeightMm = grid.unitHeightMm;
      rowPitchMm = grid.unitHeightMm;
    }

    // With a die, trust its real ups count (a lid-and-base die already holds whole sets).
    const upsPerCutSheet = isUsingExistingDie && selectedDie ? selectedDie.upsOnCutSheet : plan.ups;
    const cutSheetsPerRawSheet = CUT_FRACTION_DENOMINATOR[fraction];
    const cannotFit = upsPerCutSheet === 0;
    const upsPerRawSheet = Math.max(1, upsPerCutSheet * cutSheetsPerRawSheet);

    // Pieces, not boxes: a lid-and-base box is two flats.
    const rawSheetsNeeded = Math.ceil(((quantity * piecesPerBox) / upsPerRawSheet) * (1 + pricing.spoilageRate));

    const paperCostPerSheet =
      selectedPaperType && selectedGrammage && selectedSupplierPrice
        ? paperCostPerSheetEgp(rawSheet, selectedGrammage, selectedSupplierPrice.pricePerTonEgp)
        : 0;
    const paperCost = rawSheetsNeeded * paperCostPerSheet;
    const platesCost = printColors > 0 ? printColors * pricing.plateCostPerColorEgp : 0;
    // Press, laminator and die-cutter rates are per sheet FED, i.e. per cut sheet.
    const cutSheetsRun = rawSheetsNeeded * cutSheetsPerRawSheet;
    const pressRunCost =
      printColors > 0 ? printColors * pricing.pressRunRatePerColorPer1000SheetsEgp * (cutSheetsRun / 1000) : 0;
    const laminationCost = lamination !== 'none' ? cutSheetsRun * pricing.laminationRatePerSheetEgp[lamination] : 0;
    const dieToolingCost = isUsingExistingDie ? 0 : pricing.newDieCostEgp;
    const dieCuttingRunCost = cutSheetsRun * pricing.dieCutRatePerSheetEgp;
    // Micro-flute pizza/phone boxes ship flat (folded by the customer) — no gluing.
    const gluingCost = UNGLUED_SHAPES.includes(boxShapeId) ? 0 : quantity * pricing.glueFoldRatePerUnitEgp;

    const baseCost = paperCost + platesCost + pressRunCost + laminationCost + dieToolingCost + dieCuttingRunCost + gluingCost;
    const marginAmount = baseCost * (Math.max(0, marginPercent) / 100);
    const sellingPrice = baseCost + marginAmount;
    const unitPrice = quantity > 0 ? round2(sellingPrice / quantity) : 0;
    const totalPrice = round2(unitPrice * quantity);

    return {
      flatWidthCm: round2(flatWidthMm / 10),
      flatHeightCm: round2(flatHeightMm / 10),
      rawSheetsNeeded,
      upsPerRawSheet,
      upsPerCutSheet,
      paperCostPerSheet: round2(paperCostPerSheet),
      paperCost: round2(paperCost),
      platesCost: round2(platesCost),
      pressRunCost: round2(pressRunCost),
      laminationCost: round2(laminationCost),
      dieToolingCost,
      dieCuttingRunCost: round2(dieCuttingRunCost),
      gluingCost: round2(gluingCost),
      baseCost: round2(baseCost),
      marginAmount: round2(marginAmount),
      unitPrice,
      totalPrice,
      // for the imposition preview
      cutSheetWidthMm: cutSheet.widthMm,
      cutSheetHeightMm: cutSheet.heightMm,
      cols,
      rows,
      unitWidthMm,
      unitHeightMm,
      rowPitchMm,
      useInterlock,
      cannotFit,
      piecesPerBox,
      boxesPerCutSheet: Math.floor(upsPerCutSheet / piecesPerBox),
      sheetName: `${rawSheet.widthCm}×${rawSheet.heightCm}`,
      sheetWidthCm: rawSheet.widthCm,
      sheetHeightCm: rawSheet.heightCm,
      fraction,
      interlockPitchMm: shapeCapableOfInterlock ? interlockPitch : null,
    };
  }, [flatDims, quantity, printColors, lamination, isUsingExistingDie, selectedDie, selectedPaperType, selectedGrammage, selectedSupplierPrice, interlockEnabled, shapeCapableOfInterlock, pricing, marginPercent]);

  // --- Copy to clipboard ---------------------------------------------------
  const copyText = useMemo(() => {
    const dieLabel =
      isUsingExistingDie && selectedDie ? `تسعير بدون اسطامبه (اسطامبة رقم ${selectedDie.code})` : 'اسطامبه جديدة';
    const lines = [
      `- (طول ${boxLengthCm} X عرض ${boxWidthCm} X عمق ${boxDepthCm})`,
      `- ${selectedPaperType ? selectedPaperType.name : ''} ${selectedGrammage ? `${selectedGrammage.gsm} جرام` : ''}${selectedSupplierPrice ? ` (${selectedSupplierPrice.supplierName})` : ''}`,
      `- ${dieLabel}`,
      `- طباعة ${printColors === 0 ? 'سادة' : `${printColors} لون`}`,
      `- سلوفان ${lamination === 'none' ? 'بدون' : lamination === 'matte' ? 'مط حراري' : 'لامع'}`,
      `- عدد ${quantity.toLocaleString('ar-EG')} علبة`,
      `- سعر العلبه ${calc.unitPrice.toFixed(2)} ج`,
      `- الاجمالي ${calc.totalPrice.toLocaleString('ar-EG')} ج`,
    ];
    return lines.join('\n');
  }, [boxLengthCm, boxWidthCm, boxDepthCm, selectedPaperType, selectedGrammage, selectedSupplierPrice, isUsingExistingDie, selectedDie, printColors, lamination, quantity, calc]);

  const handleCopy = useCallback(() => {
    void navigator.clipboard?.writeText(copyText).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    });
  }, [copyText]);

  // Why the order can't be confirmed yet (null = OK). Also shown under the button.
  const confirmBlocker: string | null = !selectedPaperType || !selectedGrammage
    ? 'اختار نوع الورق والجرام الأول'
    : !selectedSupplierPrice
      ? 'الجرام ده مالوش سعر مورد مسجل — ضيفه من صفحة أنواع الورق'
      : calc.cannotFit
        ? 'العلبة بالمقاس ده مش بتدخل الفرخ — راجع المقاسات أو الاسطمبة'
        : null;

  const handleConfirm = useCallback(() => {
    if (!selectedPaperType || !selectedGrammage || !selectedSupplierPrice || calc.cannotFit) return;
    onConfirmOrder?.({
      boxType: boxTypeId,
      shape: boxShapeId,
      dimensions: { lengthCm: boxLengthCm, widthCm: boxWidthCm, depthCm: boxDepthCm },
      quantity,
      paperTypeName: selectedPaperType.name,
      gsm: selectedGrammage.gsm,
      supplierName: selectedSupplierPrice?.supplierName ?? null,
      pricePerTonEgp: selectedSupplierPrice?.pricePerTonEgp ?? null,
      printColors,
      lamination,
      isUsingExistingDie,
      matchedDie: selectedDie,
      unitPriceEgp: calc.unitPrice,
      totalPriceEgp: calc.totalPrice,
      baseCostEgp: calc.baseCost,
      rawSheetsNeeded: calc.rawSheetsNeeded,
      upsPerRawSheet: calc.upsPerRawSheet,
      interlocked: calc.useInterlock,
      paperTypeId: selectedPaperType.id,
      grammageId: selectedGrammage.id,
      supplierPriceId: selectedSupplierPrice?.id ?? null,
      dieId: isUsingExistingDie && selectedDie ? selectedDie.id : null,
      marginPercent,
      marginAmountEgp: calc.marginAmount,
      flatWidthMm: flatDims.flatWidthMm,
      flatHeightMm: flatDims.flatHeightMm,
      interlockEnabled,
      interlockPitchMm: calc.interlockPitchMm,
      piecesPerBox: calc.piecesPerBox,
      sheetWidthCm: calc.sheetWidthCm,
      sheetHeightCm: calc.sheetHeightCm,
      cutFraction: calc.fraction,
      costBreakdown: {
        paperCost: calc.paperCost,
        platesCost: calc.platesCost,
        pressRunCost: calc.pressRunCost,
        laminationCost: calc.laminationCost,
        dieToolingCost: calc.dieToolingCost,
        dieCuttingRunCost: calc.dieCuttingRunCost,
        gluingCost: calc.gluingCost,
      },
    });
  }, [selectedPaperType, selectedGrammage, selectedSupplierPrice, boxTypeId, boxShapeId, boxLengthCm, boxWidthCm, boxDepthCm, quantity, printColors, lamination, isUsingExistingDie, selectedDie, calc, marginPercent, flatDims, interlockEnabled, onConfirmOrder]);

  return (
    <div dir="rtl" className={`bg-slate-950 border border-slate-800 rounded-2xl p-4 lg:p-6 text-slate-100 ${className}`}>
      {/* Header badge */}
      <div className="mb-6 flex justify-center">
        <span className="inline-flex items-center gap-2 rounded-full bg-amber-400/10 border border-amber-400/30 text-amber-300 text-sm px-4 py-1.5">
          <BoxIcon className="w-4 h-4" />
          حاسبة العلب ومطابقة الاسطمبات
        </span>
      </div>

      {/* Mobile compact price banner */}
      <div className="lg:hidden sticky top-0 z-10 mb-4 flex items-center justify-between gap-3 rounded-xl bg-slate-900 border border-slate-800 px-4 py-3">
        <div className="flex gap-4 text-sm">
          <div>
            <div className="text-slate-400">سعر العلبه</div>
            <div className="font-semibold text-emerald-400">{calc.unitPrice.toFixed(2)} ج</div>
          </div>
          <div>
            <div className="text-slate-400">الاجمالي</div>
            <div className="font-semibold text-emerald-400">{calc.totalPrice.toLocaleString('ar-EG')} ج</div>
          </div>
        </div>
        <button onClick={handleCopy} className="flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-2 text-xs hover:bg-slate-700">
          {copied ? <Check className="w-4 h-4 text-emerald-400" /> : <Copy className="w-4 h-4" />}
          نسخ
        </button>
      </div>

      {/* Repeat a past job */}
      {pastJobs.length > 0 && (
        <div className="mb-4">
          <button
            onClick={() => setShowPastJobs((v) => !v)}
            className="w-full flex items-center justify-between rounded-xl bg-slate-900 border border-slate-800 px-3 py-2.5 text-xs text-slate-300 hover:border-slate-700"
          >
            <span>الشغلات السابقة — كرر نفس الشغلانة بدل ما تدخّل كل حاجة من الأول</span>
            <RefreshCw className="w-3.5 h-3.5 text-slate-400" />
          </button>
          {showPastJobs && (
            <div className="mt-2 rounded-xl bg-slate-900 border border-slate-800 p-2 space-y-1.5">
              {pastJobs.map((job) => (
                <button
                  key={job.id}
                  onClick={() => handleRepeatJob(job)}
                  className="w-full flex items-center justify-between rounded-lg bg-slate-800/60 hover:bg-slate-800 px-3 py-2 text-xs text-right"
                >
                  <span>{job.label}</span>
                  <span className="text-sky-300">كرر</span>
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Box type presets */}
      <div className="mb-4 rounded-xl bg-slate-900 border border-slate-800 p-3">
        <div className="text-xs text-slate-400 mb-2">نوع العلبة</div>
        <div className="flex flex-wrap gap-2">
          {BOX_TYPE_PRESETS.map((preset) => (
            <button
              key={preset.id}
              onClick={() => handleSelectBoxType(preset)}
              className={`text-xs rounded-lg px-3 py-1.5 border transition-colors ${
                boxTypeId === preset.id
                  ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                  : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
              }`}
            >
              {preset.label}
            </button>
          ))}
        </div>
        <p className="mt-2 text-[11px] text-amber-400/80">{activeBoxTypePreset.note}</p>
      </div>

      {/* Imposition preview */}
      <div className="mb-4">
        <div className="rounded-xl bg-slate-900 border border-slate-800 p-3">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-slate-300 flex items-center gap-1.5">
              <LayoutGrid className="w-3.5 h-3.5 text-slate-400" />
              مونتاج الفرخ — {calc.upsPerCutSheet} علبة/فرخ
            </span>
            {shapeCapableOfInterlock && !(isUsingExistingDie && selectedDie) && (
              <button
                onClick={() => setInterlockEnabled((v) => !v)}
                className={`text-[11px] rounded-full px-2.5 py-1 border transition-colors ${
                  interlockEnabled
                    ? 'bg-amber-500/15 border-amber-500 text-amber-300'
                    : 'bg-slate-800/60 border-slate-700 text-slate-400'
                }`}
              >
                تصميم متداخل {interlockEnabled ? 'مفعّل' : 'متوقف'}
              </button>
            )}
          </div>
          <ImpositionPreview
            sheetWidthMm={calc.cutSheetWidthMm}
            sheetHeightMm={calc.cutSheetHeightMm}
            marginTopMm={GRIPPER_ALLOWANCE_MM}
            marginSideMm={SIDE_TRIM_MM}
            cols={calc.cols}
            rows={calc.rows}
            unitWidthMm={calc.unitWidthMm}
            unitHeightMm={calc.unitHeightMm}
            rowPitchMm={calc.rowPitchMm}
            interlocked={calc.useInterlock}
          />
          {calc.useInterlock && (
            <p className="mt-1.5 text-[11px] text-amber-400/80">
              تقدير تقريبي للتوفير بالتعشيق (~{Math.round((1 - INTERLOCK_HEIGHT_SAVING_RATIO) * 100)}%) — يتظبط بالظبط مع الرسم المرجعي
            </p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        {/* Column: المدخلات — first in DOM so it sits top-on-mobile / right-on-desktop (RTL) */}
        <section className="rounded-xl bg-slate-900 border border-slate-800 p-4">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-semibold flex items-center gap-2">
              <Printer className="w-4 h-4 text-slate-400" />
              المدخلات
            </h3>
            <button
              onClick={() => setShowPaperManager((v) => !v)}
              className="flex items-center gap-1 text-[11px] text-slate-400 hover:text-slate-200"
            >
              <Settings2 className="w-3.5 h-3.5" />
              إدارة الورق
            </button>
          </div>

          <div className="space-y-4">
            {/* Paper type */}
            <div>
              <label className="block text-xs text-slate-400 mb-1.5">نوع الكرتون</label>
              <div className="grid grid-cols-2 gap-2">
                {paperCatalog.map((p) => (
                  <button
                    key={p.id}
                    onClick={() => {
                      setPaperTypeId(p.id);
                      const firstGrammage = p.grammages[0];
                      setGrammageId(firstGrammage?.id ?? '');
                      setSupplierPriceId(cheapestPrice(firstGrammage)?.id ?? null);
                    }}
                    className={`text-xs rounded-lg px-2.5 py-2 text-right border transition-colors ${
                      paperTypeId === p.id
                        ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                        : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
                    }`}
                  >
                    {p.name}
                  </button>
                ))}
              </div>
            </div>

            {/* Grammage, filtered by selected paper type */}
            <div>
              <label className="block text-xs text-slate-400 mb-1.5">الجرام</label>
              <div className="flex flex-wrap gap-1.5">
                {selectedPaperType?.grammages.map((g) => (
                  <button
                    key={g.id}
                    onClick={() => {
                      setGrammageId(g.id);
                      setSupplierPriceId(cheapestPrice(g)?.id ?? null);
                    }}
                    className={`text-xs rounded-lg px-2.5 py-1.5 border transition-colors ${
                      grammageId === g.id
                        ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                        : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
                    }`}
                    title={cheapestPrice(g) ? `أرخص سعر: ${cheapestPrice(g)!.pricePerTonEgp.toLocaleString('ar-EG')} ج/طن` : 'مفيش سعر مسجل'}
                  >
                    {g.gsm} جم
                  </button>
                ))}
                {(!selectedPaperType || selectedPaperType.grammages.length === 0) && (
                  <span className="text-xs text-slate-500">مفيش جرامات مسجلة — ضيفها من "إدارة الورق"</span>
                )}
              </div>
            </div>

            {/* Supplier price comparison for the selected grammage */}
            {selectedGrammage && (
              <div>
                <label className="block text-xs text-slate-400 mb-1.5">مقارنة الموردين ({selectedGrammage.gsm} جم)</label>
                <div className="space-y-1.5">
                  {sortedSupplierPrices.map((price, idx) => (
                    <button
                      key={price.id}
                      onClick={() => setSupplierPriceId(price.id)}
                      className={`w-full flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs border transition-colors ${
                        supplierPriceId === price.id
                          ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                          : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
                      }`}
                    >
                      <span className="flex items-center gap-1.5">
                        {price.supplierName}
                        {idx === 0 && (
                          <span className="rounded-full bg-emerald-500/15 text-emerald-400 text-[10px] px-1.5 py-0.5 border border-emerald-500/30">
                            الأرخص
                          </span>
                        )}
                      </span>
                      <span>{price.pricePerTonEgp.toLocaleString('ar-EG')} ج/طن</span>
                    </button>
                  ))}
                  {sortedSupplierPrices.length === 0 && (
                    <span className="text-xs text-slate-500">مفيش سعر مسجل لهذا الجرام — ضيفه من "إدارة الورق"</span>
                  )}
                </div>
                {selectedSupplierPrice && (
                  <div className="mt-1.5 text-[11px] text-slate-500">تكلفة الفرخ التقريبية: {calc.paperCostPerSheet.toFixed(2)} ج</div>
                )}
              </div>
            )}

            {/* Paper manager panel */}
            {showPaperManager && (
              <div className="rounded-lg bg-slate-950 border border-slate-800 p-3 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-slate-300">إدارة الورق</span>
                  <button onClick={() => setShowPaperManager(false)} className="text-slate-500 hover:text-slate-300">
                    <X className="w-3.5 h-3.5" />
                  </button>
                </div>
                {/* Additions here only live in this browser tab (quick "what if" pricing). */}
                <p className="text-[11px] text-amber-400/80">
                  الإضافة هنا للتجربة في التسعير بس ومش بتتحفظ — عشان تأكد طلب بورق جديد، ضيفه الأول من صفحة أنواع الورق.
                </p>

                {/* Existing grammages + their supplier prices for selected paper, with delete */}
                {selectedPaperType && (
                  <div className="space-y-2">
                    <div className="text-[11px] text-slate-500">جرامات وموردين {selectedPaperType.name}</div>
                    {selectedPaperType.grammages.map((g) => (
                      <div key={g.id} className="rounded bg-slate-900 px-2 py-1.5 space-y-1">
                        <div className="flex items-center justify-between text-xs font-medium">
                          <span>{g.gsm} جم</span>
                          <button onClick={() => handleRemoveGrammage(selectedPaperType.id, g.id)} className="text-red-400 hover:text-red-300">
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                        {g.prices.map((pr) => (
                          <div key={pr.id} className="flex items-center justify-between text-[11px] text-slate-400 pr-2">
                            <span>
                              {pr.supplierName} — {pr.pricePerTonEgp.toLocaleString('ar-EG')} ج/طن
                            </span>
                            <button onClick={() => handleRemoveSupplierPrice(selectedPaperType.id, g.id, pr.id)} className="text-red-400 hover:text-red-300">
                              <Trash2 className="w-3 h-3" />
                            </button>
                          </div>
                        ))}
                      </div>
                    ))}
                  </div>
                )}

                {/* Shared fields for the three add actions below */}
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <span className="block text-[10px] text-slate-500 mb-1">اسم المورد</span>
                    <input
                      type="text"
                      placeholder="مثال: شركة النصر للورق"
                      value={newSupplierName}
                      onChange={(e) => setNewSupplierName(e.target.value)}
                      className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                    />
                  </div>
                  <div>
                    <span className="block text-[10px] text-slate-500 mb-1">سعر الطن (ج)</span>
                    <input
                      type="number"
                      value={newGrammagePrice}
                      onChange={(e) => setNewGrammagePrice(Number(e.target.value))}
                      className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                    />
                  </div>
                </div>

                <button
                  onClick={handleAddSupplierPriceToSelectedGrammage}
                  className="w-full flex items-center justify-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs py-2"
                >
                  <Plus className="w-3.5 h-3.5" />
                  إضافة سعر مورد لجرام {selectedGrammage?.gsm ?? '—'}
                </button>

                <div className="grid grid-cols-2 gap-2 items-end">
                  <div>
                    <span className="block text-[10px] text-slate-500 mb-1">أو جرام جديد لنفس النوع</span>
                    <input
                      type="number"
                      value={newGrammageGsm}
                      onChange={(e) => setNewGrammageGsm(Number(e.target.value))}
                      className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                    />
                  </div>
                  <button
                    onClick={handleAddGrammageToSelectedPaper}
                    className="flex items-center justify-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs py-2"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    إضافة الجرام (بالمورد والسعر اللي فوق)
                  </button>
                </div>

                <div className="border-t border-slate-800 pt-3 space-y-2">
                  <div className="text-[11px] text-slate-500">أو نوع ورق جديد بالكامل (بالجرام والمورد والسعر اللي فوق)</div>
                  <input
                    type="text"
                    placeholder="اسم النوع، مثال: كرتون كوشيه مطفي"
                    value={newPaperName}
                    onChange={(e) => setNewPaperName(e.target.value)}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                  />
                  <select
                    value={newPaperCategory}
                    onChange={(e) => setNewPaperCategory(e.target.value as PaperCategory)}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-1.5 text-xs"
                  >
                    <option value="duplex_grey_back">دوبلكس ظهر رمادي</option>
                    <option value="duplex_white_back">دوبلكس ظهر أبيض</option>
                    <option value="bristol_white_back">بريستول ظهر أبيض</option>
                    <option value="kraft_liner">كرافت</option>
                    <option value="couche">كوشيه</option>
                    <option value="triplex_board">تريبلكس</option>
                    <option value="micro_flute">كرتون مايكرو</option>
                  </select>
                  <button
                    onClick={handleAddNewPaperType}
                    className="w-full flex items-center justify-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs py-2"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    إضافة نوع الورق
                  </button>
                </div>
              </div>
            )}

            <div>
              <label className="block text-xs text-slate-400 mb-1.5">العدد المطلوب</label>
              <input
                type="number"
                min={1}
                value={quantity}
                onChange={(e) => setQuantity(Math.max(1, Number(e.target.value)))}
                className="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1.5">المقاس (بالسنتيمتر سم)</label>
              <div className="grid grid-cols-3 gap-2">
                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">طول</span>
                  <input
                    type="number"
                    min={1}
                    value={boxLengthCm}
                    onChange={(e) => setBoxLengthCm(Math.max(1, Number(e.target.value)))}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-sky-500"
                  />
                </div>
                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">عرض</span>
                  <input
                    type="number"
                    min={1}
                    value={boxWidthCm}
                    onChange={(e) => setBoxWidthCm(Math.max(1, Number(e.target.value)))}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-sky-500"
                  />
                </div>
                <div>
                  <span className="block text-[10px] text-slate-500 mb-1">عمق</span>
                  <input
                    type="number"
                    min={1}
                    value={boxDepthCm}
                    onChange={(e) => setBoxDepthCm(Math.max(1, Number(e.target.value)))}
                    className="w-full rounded-lg bg-slate-800 border border-slate-700 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-sky-500"
                  />
                </div>
              </div>
            </div>

            {/* Dieline (2D) preview — sits right under the size inputs so it's obviously tied to them */}
            <div className="rounded-lg bg-slate-950 border border-slate-800 p-3">
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-medium text-slate-300 flex items-center gap-1.5">
                  <Ruler className="w-3.5 h-3.5 text-slate-400" />
                  تراسيه العلبة (2D) — {calc.flatWidthCm}×{calc.flatHeightCm} سم
                </span>
              </div>
              <DielinePreview dims={flatDims} />
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1.5">شكل العلبة (لحساب المفروط)</label>
              <select
                value={boxShapeId}
                onChange={(e) => setBoxShapeId(e.target.value as BoxShapeId)}
                className="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
              >
                {(Object.keys(BOX_SHAPE_LABELS) as BoxShapeId[]).map((s) => (
                  <option key={s} value={s}>
                    {BOX_SHAPE_LABELS[s]}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1.5">الطباعة</label>
              <div className="grid grid-cols-5 gap-1.5">
                {[0, 1, 2, 3, 4].map((n) => (
                  <button
                    key={n}
                    onClick={() => setPrintColors(n)}
                    className={`rounded-lg py-2 text-xs border transition-colors ${
                      printColors === n
                        ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                        : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
                    }`}
                  >
                    {n === 0 ? 'سادة' : n}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1.5 flex items-center gap-1.5">
                <Layers className="w-3.5 h-3.5" />
                السلوفان
              </label>
              <div className="grid grid-cols-3 gap-1.5">
                {(
                  [
                    ['none', 'بدون'],
                    ['matte', 'مط حراري'],
                    ['gloss', 'لامع'],
                  ] as [LaminationType, string][]
                ).map(([val, label]) => (
                  <button
                    key={val}
                    onClick={() => setLamination(val)}
                    className={`rounded-lg py-2 text-xs border transition-colors ${
                      lamination === val
                        ? 'bg-sky-500/15 border-sky-500 text-sky-300'
                        : 'bg-slate-800/60 border-slate-700 text-slate-300 hover:border-slate-600'
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Column: حالة الاسطمبه */}
        <section className="rounded-xl bg-slate-900 border border-amber-500/30 p-4">
          <h3 className="text-base font-semibold mb-4 flex items-center gap-2 text-amber-300">
            <Scissors className="w-4 h-4" />
            حالة الاسطمبه
          </h3>

          {closestDie ? (
            <div className="space-y-3">
              <div className="rounded-lg bg-slate-950/60 border border-slate-800 p-3">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium">أقرب اسطمبه: {closestDie.die.code}</span>
                  {closestDie.isExactMatch && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 text-emerald-400 text-[11px] px-2 py-0.5 border border-emerald-500/30">
                      <CheckCircle2 className="w-3 h-3" />
                      متطابقة تماماً 100%
                    </span>
                  )}
                </div>
                <div className="text-xs text-slate-400 space-y-1">
                  <div>
                    ({closestDie.die.lengthCm.toFixed(1)} X {closestDie.die.widthCm.toFixed(1)} X{' '}
                    {closestDie.die.depthCm.toFixed(1)})
                  </div>
                  <div className="flex items-center gap-1">
                    <MapPin className="w-3 h-3" />
                    مكان الرف: {closestDie.die.rackLocation}
                  </div>
                  {!closestDie.isExactMatch && (
                    <div className="text-amber-400">فرق التطابق: {round2(closestDie.diffCm * 10)} مم</div>
                  )}
                  {closestDie.die.condition !== 'ready' && (
                    <div className="text-red-400">
                      حالة الاسطمبة: {closestDie.die.condition === 'needs_rubber' ? 'محتاجة مطاط' : 'تحت الصيانة'}
                    </div>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <button
                  onClick={handleUseExistingDieSize}
                  className="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm py-2.5 font-medium transition-colors"
                >
                  <RefreshCw className="w-4 h-4" />
                  قرب وسعر (بمقاس الاسطامبة)
                </button>
                <button
                  onClick={handleRequestNewDie}
                  className="w-full flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-800/60 hover:bg-slate-800 text-slate-100 text-sm py-2.5 transition-colors"
                >
                  <Scissors className="w-4 h-4" />
                  اسطامبه جديدة (+{pricing.newDieCostEgp} ج)
                </button>
                <button
                  onClick={handlePriceWithoutNewDie}
                  className="w-full flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-800/60 hover:bg-slate-800 text-slate-100 text-sm py-2.5 transition-colors"
                >
                  استخدم أقرب اسطمبة موجودة (توفير تكلفة الاسطمبة)
                </button>
              </div>
            </div>
          ) : (
            <p className="text-sm text-slate-400">لا توجد اسطمبات مسجلة بعد.</p>
          )}
        </section>

        {/* Column: التفاصيل */}
        <section className="rounded-xl bg-slate-900 border border-slate-800 p-4 flex flex-col lg:sticky lg:top-4 lg:self-start">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-semibold">التفاصيل</h3>
            <button
              onClick={handleCopy}
              className="flex items-center gap-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 px-2.5 py-1.5 text-xs transition-colors"
            >
              {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
              {copied ? 'تم النسخ!' : 'نسخ النص'}
            </button>
          </div>

          <div className="text-sm text-slate-300 space-y-1.5 flex-1">
            <div>
              - (طول {boxLengthCm} X عرض {boxWidthCm} X عمق {boxDepthCm})
            </div>
            <div>
              - {selectedPaperType?.name} {selectedGrammage ? `${selectedGrammage.gsm} جرام` : ''}
              {selectedSupplierPrice ? ` (${selectedSupplierPrice.supplierName})` : ''}
            </div>
            <div>
              - {isUsingExistingDie && selectedDie ? `تسعير بدون اسطامبه (اسطامبة رقم ${selectedDie.code})` : 'اسطامبه جديدة'}
            </div>
            <div>- طباعة {printColors === 0 ? 'سادة' : `${printColors} لون`}</div>
            <div>- سلوفان {lamination === 'none' ? 'بدون' : lamination === 'matte' ? 'مط حراري' : 'لامع'}</div>
            <div>- عدد {quantity.toLocaleString('ar-EG')} علبة</div>
            <div>
              - مونتاج {calc.piecesPerBox > 1 ? `${calc.upsPerCutSheet} قطعة (${calc.boxesPerCutSheet} قاع + ${calc.boxesPerCutSheet} غطاء)` : `${calc.upsPerCutSheet} علبة`}/{CUT_FRACTION_LABELS[calc.fraction]} فرخ {calc.sheetName}{calc.useInterlock ? ' (متداخل)' : ''} — {calc.rawSheetsNeeded.toLocaleString('ar-EG')} فرخ مطلوب
            </div>
          </div>

          <div className="my-4 border-t border-slate-800" />

          {/* Manual margin — typed per job (business rule: no fixed markup) */}
          <div className="mb-3">
            <label className="block text-xs text-slate-400 mb-1.5">نسبة الربح %</label>
            <input
              type="number"
              min={0}
              value={marginPercent}
              onChange={(e) => setMarginPercent(Math.max(0, Number(e.target.value)))}
              className="w-full rounded-lg bg-slate-900 border border-slate-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-400">التكلفة</span>
              <span>{calc.baseCost.toLocaleString('ar-EG')} ج</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-400">الربح ({marginPercent}%)</span>
              <span>{calc.marginAmount.toLocaleString('ar-EG')} ج</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-400">سعر العلبه</span>
              <span className="font-semibold">{calc.unitPrice.toFixed(2)} ج</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-400 text-sm">الاجمالي</span>
              <span className="text-2xl font-bold text-emerald-400 tabular-nums">{calc.totalPrice.toLocaleString('ar-EG')} ج</span>
            </div>
          </div>

          <button
            onClick={handleConfirm}
            disabled={confirmBlocker !== null}
            className="mt-4 w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm py-2.5 font-medium transition-colors"
          >
            أكد الطلب وسجل العميل
          </button>
          {confirmBlocker && <p className="mt-2 text-xs text-amber-400">{confirmBlocker}</p>}
        </section>
      </div>
    </div>
  );
}
