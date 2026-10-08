/**
 * IBBS — Exportar una tabla a CSV (Excel).
 *
 * Utilidad genérica: IbbsExport.table('#idDeLaTabla', 'nombre-archivo')
 * lee las filas visibles del <table> en pantalla y descarga un .csv.
 * La columna se salta automáticamente si su encabezado (<th>) viene
 * vacío (p. ej. una casilla de selección) o dice "Acciones"/"Acción"
 * (los botones de editar/eliminar no tienen sentido en una planilla).
 */
(function (window, document) {
    'use strict';

    function csvEscape(v) {
        v = String(v == null ? '' : v);
        if (/[",\n;]/.test(v)) return '"' + v.replace(/"/g, '""') + '"';
        return v;
    }

    function tableToCSV(table) {
        var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
        if (!rows.length) return '';

        var headerCells = Array.prototype.slice.call(rows[0].querySelectorAll('th,td'));
        var skip = headerCells.map(function (c) {
            var t = c.textContent.trim();
            return t === '' || /^acci[oó]n(es)?$/i.test(t);
        });

        var out = [];
        rows.forEach(function (tr) {
            var cells = Array.prototype.slice.call(tr.querySelectorAll('th,td'));
            if (cells.length < headerCells.length) return; // fila "cargando…"/"sin resultados" con colspan
            var vals = [];
            cells.forEach(function (cell, i) {
                if (skip[i]) return;
                vals.push(csvEscape(cell.textContent.trim().replace(/\s+/g, ' ')));
            });
            if (vals.some(function (v) { return v !== ''; })) out.push(vals.join(','));
        });
        return out.join('\r\n');
    }

    function download(filename, content) {
        var blob = new Blob(['﻿' + content], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(url); }, 0);
    }

    function exportTable(selector, filename) {
        var table = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!table) return;
        var csv = tableToCSV(table);
        if (!csv) {
            if (window.Ibbs && window.Ibbs.warn) window.Ibbs.warn('No hay datos para exportar todavía.');
            return;
        }
        download((filename || 'ibbs-export') + '.csv', csv);
        if (window.Ibbs && window.Ibbs.success) window.Ibbs.success('Descarga lista.');
    }

    /**
     * Exportar a partir de datos en memoria (headers + array de filas,
     * cada fila un array de valores) en vez de leer el DOM. Hace falta
     * para una tabla paginada en pantalla (p. ej. Historial de
     * asistencias): el <table> visible solo tiene la página actual,
     * pero esto exporta el set completo ya filtrado.
     */
    function exportRows(headers, rows, filename) {
        if (!rows || !rows.length) {
            if (window.Ibbs && window.Ibbs.warn) window.Ibbs.warn('No hay datos para exportar todavía.');
            return;
        }
        var out = [headers.map(csvEscape).join(',')];
        rows.forEach(function (r) { out.push(r.map(csvEscape).join(',')); });
        download((filename || 'ibbs-export') + '.csv', out.join('\r\n'));
        if (window.Ibbs && window.Ibbs.success) window.Ibbs.success('Descarga lista.');
    }

    window.IbbsExport = { table: exportTable, rows: exportRows, csvEscape: csvEscape };
})(window, document);
