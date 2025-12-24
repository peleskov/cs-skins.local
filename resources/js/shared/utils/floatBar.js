/**
 * Утилиты для работы с полоской износа (Float Bar)
 */

// Диапазоны износа CS2
export const WEAR_RANGES = {
    fn: [0.00, 0.07],    // Factory New
    mw: [0.07, 0.15],    // Minimal Wear
    ft: [0.15, 0.38],    // Field-Tested
    ww: [0.38, 0.45],    // Well-Worn
    bs: [0.45, 1.00]     // Battle-Scarred
};

/**
 * Проверяет, есть ли у предмета диапазон float
 * @param {Object} item - предмет с данными о float
 * @returns {boolean}
 */
export function hasFloatRange(item) {
    // Если есть точные значения min/max - используем их
    if (item.float_min !== null && item.float_min !== undefined &&
        item.float_max !== null && item.float_max !== undefined &&
        item.float_max > item.float_min) {
        return true;
    }
    
    // Если есть только float_value или wear_value - тоже можем показать бар
    return !!(item.float_value || item.wear_value);
}

/**
 * Получает минимальное и максимальное значение float для предмета
 * @param {Object} item - предмет с данными о float
 * @returns {Object} {min: number, max: number}
 */
export function getFloatRange(item) {
    // Если есть точные значения - используем их
    if (item.float_min !== null && item.float_min !== undefined &&
        item.float_max !== null && item.float_max !== undefined) {
        return {
            min: parseFloat(item.float_min),
            max: parseFloat(item.float_max)
        };
    }
    
    // Иначе используем стандартные диапазоны CS2 (0.00 - 1.00)
    return {
        min: 0.00,
        max: 1.00
    };
}

/**
 * Получает текущее значение float предмета
 * @param {Object} item - предмет с данными о float
 * @returns {number|null}
 */
export function getFloatValue(item) {
    // Приоритет: float_value -> wear_value
    return parseFloat(item.float_value || item.wear_value) || null;
}

/**
 * Вычисляет позицию маркера на полоске (в процентах)
 * @param {Object} item - предмет с данными о float
 * @returns {number} позиция в процентах (0-100)
 */
export function getFloatMarkerPosition(item) {
    if (!hasFloatRange(item)) return 0;
    
    const floatValue = getFloatValue(item);
    if (!floatValue) return 0;
    
    const range = getFloatRange(item);
    return ((floatValue - range.min) / (range.max - range.min)) * 100;
}

/**
 * Вычисляет ширину сегмента износа (в процентах)
 * @param {Object} item - предмет с данными о float
 * @param {string} segment - сегмент ('fn', 'mw', 'ft', 'ww', 'bs')
 * @returns {number} ширина в процентах
 */
export function getSegmentWidth(item, segment) {
    if (!hasFloatRange(item)) return 0;
    
    const range = getFloatRange(item);
    const rangeWidth = range.max - range.min;
    
    const [segmentMin, segmentMax] = WEAR_RANGES[segment];
    
    // Находим пересечение диапазона предмета с диапазоном состояния
    const overlapMin = Math.max(range.min, segmentMin);
    const overlapMax = Math.min(range.max, segmentMax);
    
    if (overlapMin >= overlapMax) return 0;
    
    const overlapWidth = overlapMax - overlapMin;
    return (overlapWidth / rangeWidth) * 100;
}

/**
 * Создает компонент Float Bar для Vue
 * @param {Object} item - предмет с данными о float
 * @param {boolean} showMinMax - показывать ли минимальные и максимальные значения
 * @param {boolean} showValue - показывать ли текущее значение float
 * @returns {Object} объект с данными для рендеринга
 */
export function createFloatBarData(item, showMinMax = false, showValue = true) {
    if (!hasFloatRange(item)) {
        return null;
    }
    
    const range = getFloatRange(item);
    const floatValue = getFloatValue(item);
    
    return {
        hasFloat: true,
        floatValue: floatValue,
        floatMin: range.min,
        floatMax: range.max,
        markerPosition: getFloatMarkerPosition(item),
        showMinMax,
        showValue,
        segments: {
            fn: getSegmentWidth(item, 'fn'),
            mw: getSegmentWidth(item, 'mw'),
            ft: getSegmentWidth(item, 'ft'),
            ww: getSegmentWidth(item, 'ww'),
            bs: getSegmentWidth(item, 'bs')
        }
    };
}