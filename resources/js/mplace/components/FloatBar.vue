<template>
  <div v-if="floatData && floatData.hasFloat" :class="['float-bar-container', { 'float-bar-short': !showMinMax && !showValue }, 'mb-2']">
    <div class="float-bar">
      <!-- Показываем мин/макс значения только если showMinMax true -->
      <div v-if="showMinMax" class="float-min-max-labels">
        <span>{{ floatData.floatMin.toFixed(2) }}</span>
        <span>{{ floatData.floatMax.toFixed(2) }}</span>
      </div>
      
      <!-- Маркер позиции -->
      <div 
        v-if="floatData.floatValue" 
        class="wear-marker"
        :style="{ left: 'calc(' + floatData.markerPosition + '% - 1px)' }"
      ></div>
      
      <!-- Значение float (показываем только если showValue true) -->
      <div
        v-if="showValue && floatData.floatValue"
        class="wear-value"
        :style="{ left: floatData.markerPosition + '%', transform: 'translateX(-' + floatData.markerPosition + '%)' }"
      >
        {{ floatData.floatValue.toFixed(6) }}
      </div>
      
      <!-- Сегменты износа -->
      <div class="float-segments d-flex h-100">
        <div 
          v-for="segment in ['fn', 'mw', 'ft', 'ww', 'bs']" 
          :key="segment"
          :class="`h-100 float-segment ${segment}`"
          :style="{ width: floatData.segments[segment] + '%' }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script>
import { createFloatBarData } from '../../shared/utils/floatBar'

export default {
  name: 'FloatBar',
  props: {
    item: {
      type: Object,
      required: true
    },
    showValue: {
      type: Boolean,
      default: true
    },
    showMinMax: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    floatData() {
      return createFloatBarData(this.item, this.showMinMax, this.showValue)
    }
  }
}
</script>