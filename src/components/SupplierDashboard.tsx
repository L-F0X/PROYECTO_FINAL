import React, { useState } from "react";
import { 
  Building2, 
  FileEdit, 
  DollarSign, 
  Percent, 
  Signature, 
  CheckCircle2, 
  Clipboard, 
  ListTodo,
  TrendingUp,
  Award
} from "lucide-react";
import { 
  LoteRequerimiento, 
  MatrizItem, 
  Cotizacion, 
  IvaCotizacion, 
  FichaTecnica, 
  Proveedor 
} from "../types";

interface SupplierDashboardProps {
  proveedores: Proveedor[];
  lotes: LoteRequerimiento[];
  matrizItems: MatrizItem[];
  cotizaciones: Cotizacion[];
  ivas: IvaCotizacion[];
  fichasTecnicas: FichaTecnica[];
  onUpdateLotes: (updated: LoteRequerimiento[]) => void;
  onUpdateMatrizItems: (updated: MatrizItem[]) => void;
  onUpdateCotizaciones: (updated: Cotizacion[]) => void;
  onUpdateIvas: (updated: IvaCotizacion[]) => void;
  onUpdateFichasTecnicas: (updated: FichaTecnica[]) => void;
}

export default function SupplierDashboard({
  proveedores,
  lotes,
  matrizItems,
  cotizaciones,
  ivas,
  fichasTecnicas,
  onUpdateLotes,
  onUpdateMatrizItems,
  onUpdateCotizaciones,
  onUpdateIvas,
  onUpdateFichasTecnicas
}: SupplierDashboardProps) {
  const [selectedProviderId, setSelectedProviderId] = useState<number>(401);
  const [selectedLoteId, setSelectedLoteId] = useState<number | null>(
    lotes.find(l => l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" || l.ESTADO_TRAMITE === "COTIZADO")?.ID_LOTE || lotes[0]?.ID_LOTE || null
  );

  // Form states for adding/editing quote
  const [selectedItemForQuote, setSelectedItemForQuote] = useState<MatrizItem | null>(null);
  const [valorUnitario, setValorUnitario] = useState<number>(0);
  const [ivaPorcentaje, setIvaPorcentaje] = useState<number>(19);

  // Form states for Ficha Tecnica
  const [denominacionTecnica, setDenominacionTecnica] = useState("");
  const [unidadFisica, setUnidadFisica] = useState("Unidad");
  const [descripcionGeneral, setDescripcionGeneral] = useState("");
  const [marcaOfrecida, setMarcaOfrecida] = useState("");
  const [firmaProponente, setFirmaProponente] = useState("");

  const activeProvider = proveedores.find((p) => p.ID_PROVEEDOR === selectedProviderId);

  // Lots that can be quoted (CON_CERTIFICADO_NO_EXISTENCIA or COTIZADO or PROCESADO)
  const quotableLots = lotes.filter((l) => 
    l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" || 
    l.ESTADO_TRAMITE === "COTIZADO" || 
    l.ESTADO_TRAMITE === "PROCESADO"
  );

  const selectedLote = lotes.find((l) => l.ID_LOTE === selectedLoteId);
  const filteredItems = matrizItems.filter((i) => i.ID_LOTE === selectedLoteId);

  // Load existing quote for this item and supplier
  const getExistingQuoteInfo = (itemId: number) => {
    const q = cotizaciones.find((c) => c.ID_MATRIZ_ITEM === itemId && c.ID_PROVEEDOR === selectedProviderId);
    if (!q) return null;
    const i = ivas.find((v) => v.ID_COTIZACON === q.ID_COTIZACION);
    return { q, i };
  };

  const openQuoteModal = (item: MatrizItem) => {
    setSelectedItemForQuote(item);
    const existing = getExistingQuoteInfo(item.ID_MATRIZ_ITEM);
    
    if (existing) {
      setValorUnitario(existing.q.VALOR_UNITARIO);
      setIvaPorcentaje(existing.i?.PERCENTAJE_IVA || 19);
    } else {
      setValorUnitario(item.VALOR_UNITARIO_PROMEDIO || 0);
      setIvaPorcentaje(19);
    }

    // Technical sheet prefill
    const ft = fichasTecnicas.find((f) => f.NOMBRE_ITEM === item.DESCRIPCIÓN_BIEN);
    if (ft) {
      setDenominacionTecnica(ft.DENOMINACIÓN_TECNICA_BIEN);
      setUnidadFisica(ft.UNIDAD_MEDIDA);
      setDescripcionGeneral(ft.DESCRIPCIÓN_GENERAL);
      setMarcaOfrecida(ft.MARCA_OFRECIDA);
      setFirmaProponente(ft.FIRMA_PROPONENTE);
    } else {
      setDenominacionTecnica(item.DESCRIPCIÓN_BIEN);
      setUnidadFisica(item.UNIDAD_MEDIDA);
      setDescripcionGeneral("");
      setMarcaOfrecida("");
      setFirmaProponente(activeProvider ? `Ing. Técnico de ${activeProvider.RAZON_SOCIAL}` : "");
    }
  };

  const handleSaveQuote = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedItemForQuote || !selectedProviderId) return;

    const itemId = selectedItemForQuote.ID_MATRIZ_ITEM;
    const unitPrice = Number(valorUnitario);
    const qty = selectedItemForQuote.CANTIDAD_REGULAR;
    const totalPrice = unitPrice * qty;

    const existing = getExistingQuoteInfo(itemId);
    let updatedCotizaciones = [...cotizaciones];
    let updatedIvas = [...ivas];
    let qId = existing?.q.ID_COTIZACION || Date.now();

    if (existing) {
      // Update quote
      updatedCotizaciones = cotizaciones.map((c) => {
        if (c.ID_COTIZACION === existing.q.ID_COTIZACION) {
          return { ...c, VALOR_UNITARIO: unitPrice, VALOR_TOTAL: totalPrice };
        }
        return c;
      });

      // Update IVA
      updatedIvas = ivas.map((v) => {
        if (v.ID_COTIZACON === existing.q.ID_COTIZACION) {
          return {
            ...v,
            PERCENTAJE_IVA: ivaPorcentaje,
            VALOR_IVA_UNITARIO: Math.round(unitPrice * (ivaPorcentaje / 100))
          };
        }
        return v;
      });
    } else {
      // Create new quote
      const newQuote: Cotizacion = {
        ID_COTIZACION: qId,
        ID_MATRIZ_ITEM: itemId,
        ID_PROVEEDOR: selectedProviderId,
        VALOR_UNITARIO: unitPrice,
        VALOR_TOTAL: totalPrice
      };
      updatedCotizaciones.push(newQuote);

      const newIva: IvaCotizacion = {
        ID_IVA: qId + 1,
        ID_COTIZACON: qId,
        PERCENTAJE_IVA: ivaPorcentaje,
        VALOR_IVA_UNITARIO: Math.round(unitPrice * (ivaPorcentaje / 100)),
        NUMERO_OFERTA: "OFERTA_1" // Default assignment
      };
      updatedIvas.push(newIva);
    }

    // Technical Sheet saving
    const ftExisting = fichasTecnicas.find((f) => f.NOMBRE_ITEM === selectedItemForQuote.DESCRIPCIÓN_BIEN);
    let updatedFts = [...fichasTecnicas];
    if (ftExisting) {
      updatedFts = fichasTecnicas.map((f) => {
        if (f.ID_FICHA_TECNICA === ftExisting.ID_FICHA_TECNICA) {
          return {
            ...f,
            DENOMINACIÓN_TECNICA_BIEN: denominacionTecnica,
            UNIDAD_MEDIDA: unidadFisica,
            DESCRIPCIÓN_GENERAL: descripcionGeneral,
            MARCA_OFRECIDA: marcaOfrecida,
            FIRMA_PROPONENTE: firmaProponente
          };
        }
        return f;
      });
    } else {
      const newFt: FichaTecnica = {
        ID_FICHA_TECNICA: Date.now() + 5,
        NOMBRE_ITEM: selectedItemForQuote.DESCRIPCIÓN_BIEN,
        CODIGO_UNSPSC: "321216" + Math.floor(10 + Math.random() * 90),
        DENOMINACIÓN_TECNICA_BIEN: denominacionTecnica,
        UNIDAD_MEDIDA: unidadFisica,
        DESCRIPCIÓN_GENERAL: descripcionGeneral,
        MARCA_OFRECIDA: marcaOfrecida,
        FIRMA_PROPONENTE: firmaProponente
      };
      updatedFts.push(newFt);
    }

    onUpdateCotizaciones(updatedCotizaciones);
    onUpdateIvas(updatedIvas);
    onUpdateFichasTecnicas(updatedFts);

    // Dynamic Recalculation in MatrizItem!
    // We average all existing quotes (from all providers) for this item
    const allQuotesForThisItem = updatedCotizaciones.filter((c) => c.ID_MATRIZ_ITEM === itemId);
    const avgUnitPrice = Math.round(
      allQuotesForThisItem.reduce((sum, q) => sum + q.VALOR_UNITARIO, 0) / allQuotesForThisItem.length
    );

    // Let's distribute quotes to Oferta_1, Oferta_2, Oferta_3 columns in MatrizItem
    const of1 = allQuotesForThisItem[0]?.VALOR_UNITARIO || 0;
    const of2 = allQuotesForThisItem[1]?.VALOR_UNITARIO || 0;
    const of3 = allQuotesForThisItem[2]?.VALOR_UNITARIO || 0;

    const updatedMatriz = matrizItems.map((item) => {
      if (item.ID_MATRIZ_ITEM === itemId) {
        return {
          ...item,
          OFERTA_1: of1,
          OFERTA_2: of2,
          OFERTA_3: of3,
          VALOR_UNITARIO_PROMEDIO: avgUnitPrice,
          VALOR_TORAL_PROMEDIO: avgUnitPrice * qty
        };
      }
      return item;
    });

    onUpdateMatrizItems(updatedMatriz);

    // If lot is CON_CERTIFICADO_NO_EXISTENCIA, update lot status to COTIZADO!
    if (selectedLote && selectedLote.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA") {
      const updatedLotes = lotes.map((l) => {
        if (l.ID_LOTE === selectedLoteId) {
          return { ...l, ESTADO_TRAMITE: "COTIZADO" as const };
        }
        return l;
      });
      onUpdateLotes(updatedLotes);
    }

    setSelectedItemForQuote(null);
  };

  const calculateLotQuotesTotal = () => {
    return filteredItems.reduce((sum, item) => {
      const info = getExistingQuoteInfo(item.ID_MATRIZ_ITEM);
      return sum + (info ? info.q.VALOR_TOTAL : 0);
    }, 0);
  };

  return (
    <div id="supplier-workspace" className="space-y-6">
      {/* Top selection banner */}
      <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
          <div>
            <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
              <Building2 className="h-5 w-5 text-emerald-600" />
              Portal de Oferentes y Proveedores SENA
            </h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Participa en los estudios de mercado públicos cargando cotizaciones, liquidando IVA e ingresando fichas técnicas para el análisis de viabilidad.
            </p>
          </div>
          {/* Provider quick picker */}
          <div className="flex items-center gap-2">
            <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Identidad de Proveedor:</label>
            <select
              value={selectedProviderId}
              onChange={(e) => setSelectedProviderId(Number(e.target.value))}
              className="bg-emerald-50 border border-emerald-300 rounded-lg px-3 py-1.5 text-xs font-bold text-emerald-900 focus:outline-emerald-500 cursor-pointer"
            >
              {proveedores.map((p) => (
                <option key={p.ID_PROVEEDOR} value={p.ID_PROVEEDOR}>
                  {p.RAZON_SOCIAL} ({p.NIT})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Acciones e Instrucciones de Rol */}
        <div className="mt-5">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Acciones y Guía del Oferente / Proveedor</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">1. Definir Identidad</span>
              <p className="text-[10px] text-slate-500 mt-1">Usa el selector superior para alternar la firma de tu NIT en nombre de las ferreterías o distribuidoras del mercado.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">2. Cargar Cotización</span>
              <p className="text-[10px] text-slate-500 mt-1">Registra los precios comerciales de referencia de cada material liberado por el centro de formación.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">3. Desglosar Impuesto (IVA)</span>
              <p className="text-[10px] text-slate-500 mt-1">Determina de forma autónoma el porcentaje del IVA (ej. 19%) para desglosar de manera transparente el valor neto.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">4. Estructurar Ficha Técnica</span>
              <p className="text-[10px] text-slate-500 mt-1">Ingresa la marca ofrecida, especificaciones del fabricante y tu firma oficial autorizada como proponente.</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Quotable Lots List */}
        <div className="lg:col-span-1 space-y-4">
          <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-4">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1">
              <ListTodo className="w-3.5 h-3.5 text-emerald-600" />
              Lotes para Cotizar ({quotableLots.length})
            </h3>
            {quotableLots.length === 0 ? (
              <div className="p-4 text-center bg-slate-50 border border-slate-100 rounded-lg text-slate-400 text-xs font-medium">
                No hay convocatorias vigentes.
              </div>
            ) : (
              <div className="space-y-2">
                {quotableLots.map((lote) => {
                  const count = matrizItems.filter((i) => i.ID_LOTE === lote.ID_LOTE).length;
                  return (
                    <button
                      key={lote.ID_LOTE}
                      id={`supplier-lote-${lote.ID_LOTE}`}
                      onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                      className={`w-full text-left p-3 rounded-lg border transition-all flex flex-col justify-between ${
                        selectedLoteId === lote.ID_LOTE
                          ? "bg-emerald-50 border-emerald-500 text-emerald-950"
                          : "bg-slate-50 border-slate-200 hover:bg-slate-100 text-gray-700"
                      }`}
                    >
                      <span className="font-bold text-xs line-clamp-1">{lote.LOTE_NOMBRE}</span>
                      <div className="flex items-center justify-between mt-2 text-[10px]">
                        <span className="text-gray-400 font-mono font-bold">{count} Ítems</span>
                        <span className={`px-1.5 py-0.2 rounded font-semibold ${
                          lote.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" ? "bg-amber-100 text-amber-800" : "bg-emerald-100 text-emerald-800"
                        }`}>
                          {lote.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" ? "Por Cotizar" : "Cotizado"}
                        </span>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Supplier profile card */}
          {activeProvider && (
            <div className="bg-slate-50 border border-gray-200 rounded-xl p-4 text-xs space-y-2">
              <h4 className="font-bold text-gray-700">Tu Perfil de Empresa</h4>
              <div className="space-y-1 text-gray-600">
                <p><strong>Razón Social:</strong> {activeProvider.RAZON_SOCIAL}</p>
                <p><strong>NIT:</strong> {activeProvider.NIT}</p>
                <p><strong>Email Comercial:</strong> {activeProvider.EMAIL}</p>
              </div>
            </div>
          )}
        </div>

        {/* Selected Lot quotes matrix */}
        <div className="lg:col-span-3 space-y-6">
          {selectedLote ? (
            <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
              {/* Header */}
              <div className="pb-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                  <h3 className="text-base font-bold text-gray-900">{selectedLote.LOTE_NOMBRE}</h3>
                  <p className="text-xs text-gray-400 mt-1">
                    Cargue sus precios de oferta para conformar el estudio de mercado.
                  </p>
                </div>
                <div className="text-left sm:text-right bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-xl">
                  <span className="text-[10px] text-gray-500 font-bold block uppercase tracking-wider">Tu Propuesta Cargada (Subtotal)</span>
                  <span className="text-lg font-black text-emerald-800">${calculateLotQuotesTotal().toLocaleString()} COP</span>
                </div>
              </div>

              {/* Items Table */}
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-gray-100 text-gray-400 text-[10px] font-bold uppercase font-mono">
                      <th className="py-2.5 px-1">Bien / Especificación Requerida</th>
                      <th className="py-2.5 px-1 text-center">Unidad</th>
                      <th className="py-2.5 px-1 text-right">Cant. Requerida</th>
                      <th className="py-2.5 px-1 text-right">Tu Oferta Unit.</th>
                      <th className="py-2.5 px-1 text-right">IVA %</th>
                      <th className="py-2.5 px-1 text-right">Subtotal Propuesto</th>
                      <th className="py-2.5 px-1 text-center">Ficha Técnica</th>
                      <th className="py-2.5 px-1 text-center">Acción</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 text-xs">
                    {filteredItems.map((item) => {
                      const quoteInfo = getExistingQuoteInfo(item.ID_MATRIZ_ITEM);
                      const ft = fichasTecnicas.find((f) => f.NOMBRE_ITEM === item.DESCRIPCIÓN_BIEN);
                      return (
                        <tr key={item.ID_MATRIZ_ITEM} className="hover:bg-slate-50/50">
                          <td className="py-3.5 px-1 font-semibold text-gray-800">
                            {item.DESCRIPCIÓN_BIEN}
                          </td>
                          <td className="py-3.5 px-1 text-center text-gray-500">{item.UNIDAD_MEDIDA}</td>
                          <td className="py-3.5 px-1 text-right font-black text-gray-700">{item.CANTIDAD_REGULAR}</td>
                          <td className="py-3.5 px-1 text-right text-emerald-800 font-bold">
                            {quoteInfo ? `$${quoteInfo.q.VALOR_UNITARIO.toLocaleString()}` : "—"}
                          </td>
                          <td className="py-3.5 px-1 text-right text-gray-500 font-semibold">
                            {quoteInfo ? `${quoteInfo.i?.PERCENTAJE_IVA || 0}%` : "—"}
                          </td>
                          <td className="py-3.5 px-1 text-right font-black text-emerald-900">
                            {quoteInfo ? `$${quoteInfo.q.VALOR_TOTAL.toLocaleString()}` : "—"}
                          </td>
                          <td className="py-3.5 px-1 text-center">
                            {ft ? (
                              <span className="inline-block bg-emerald-50 text-emerald-800 font-bold px-1.5 py-0.5 rounded text-[10px] border border-emerald-100">
                                FT Completa
                              </span>
                            ) : (
                              <span className="text-red-500 text-[10px] font-bold">Pendiente FT</span>
                            )}
                          </td>
                          <td className="py-3.5 px-1 text-center">
                            <button
                              id={`btn-quote-item-${item.ID_MATRIZ_ITEM}`}
                              onClick={() => openQuoteModal(item)}
                              className="inline-flex items-center gap-1 bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-2 py-1 rounded text-[10px] shadow-sm transition-colors"
                            >
                              <FileEdit className="w-3 h-3" /> Cotizar
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-white border border-gray-150 rounded-xl p-12 text-center text-gray-400">
              <Building2 className="h-12 w-12 text-gray-300 mx-auto mb-2" />
              <h3 className="text-base font-bold text-gray-700">No hay convocatorias seleccionadas</h3>
              <p className="text-xs text-gray-500 mt-1">
                Haz clic en una de las convocatorias del panel lateral para cargar tus propuestas comerciales.
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Quote & Ficha Tecnica submission Modal */}
      {selectedItemForQuote && (
        <div id="modal-cotizar" className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-2xl overflow-hidden animate-scaleIn">
            {/* Header */}
            <div className="bg-emerald-950 text-white p-5 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">
                  ESTUDIO DE MERCADO Y FICHA TÉCNICA
                </span>
                <h3 className="text-lg font-black mt-0.5 line-clamp-1">{selectedItemForQuote.DESCRIPCIÓN_BIEN}</h3>
              </div>
              <button 
                onClick={() => setSelectedItemForQuote(null)}
                className="text-white hover:text-red-200 font-bold text-lg"
              >
                ✕
              </button>
            </div>

            {/* Body */}
            <form id="form-save-quote" onSubmit={handleSaveQuote} className="p-6 max-h-[500px] overflow-y-auto space-y-5">
              
              {/* Financial values */}
              <div className="bg-emerald-50/40 border border-emerald-100 rounded-xl p-4 space-y-4">
                <span className="text-xs font-bold text-emerald-900 uppercase tracking-wider block border-b border-emerald-100 pb-1">
                  1. Oferta Económica
                </span>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                      Valor Unitario (COP, Antes de IVA)
                    </label>
                    <div className="relative">
                      <DollarSign className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" />
                      <input
                        type="number"
                        min="1"
                        required
                        value={valorUnitario}
                        onChange={(e) => setValorUnitario(Number(e.target.value))}
                        className="w-full bg-white border border-gray-300 rounded-lg pl-7 pr-3 py-1.5 text-xs font-bold focus:outline-emerald-500"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                      Porcentaje de IVA (%)
                    </label>
                    <div className="relative">
                      <Percent className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" />
                      <select
                        value={ivaPorcentaje}
                        onChange={(e) => setIvaPorcentaje(Number(e.target.value))}
                        className="w-full bg-white border border-gray-300 rounded-lg pl-7 pr-3 py-1.5 text-xs font-bold focus:outline-emerald-500"
                      >
                        <option value="19">19% (General)</option>
                        <option value="5">5% (Diferencial)</option>
                        <option value="0">0% (Exento)</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <span className="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                      Cantidad Solicitada
                    </span>
                    <span className="block text-xs font-black text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-1.5 font-mono">
                      {selectedItemForQuote.CANTIDAD_REGULAR} {selectedItemForQuote.UNIDAD_MEDIDA}
                    </span>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4 text-xs font-mono font-bold pt-2 border-t border-dashed border-emerald-150">
                  <div className="text-gray-500">
                    IVA Liquidado Unitario: <span className="text-emerald-800">${Math.round(valorUnitario * (ivaPorcentaje / 100)).toLocaleString()}</span>
                  </div>
                  <div className="text-right text-emerald-950">
                    SUBTOTAL DE PROPUESTA: <span className="text-emerald-800 text-sm font-black">${(valorUnitario * selectedItemForQuote.CANTIDAD_REGULAR).toLocaleString()} COP</span>
                  </div>
                </div>
              </div>

              {/* Ficha Tecnica details */}
              <div className="border border-slate-200 rounded-xl p-4 space-y-4">
                <span className="text-xs font-bold text-slate-700 uppercase tracking-wider block border-b border-slate-100 pb-1">
                  2. Especificación Técnica (Ficha Técnica PDF-Form)
                </span>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[10px] font-semibold text-gray-500 uppercase">Denominación Técnica del Bien</label>
                    <input
                      type="text"
                      required
                      value={denominacionTecnica}
                      onChange={(e) => setDenominacionTecnica(e.target.value)}
                      className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-semibold text-gray-500 uppercase">Unidad de Presentación Ofrecida</label>
                    <input
                      type="text"
                      required
                      value={unidadFisica}
                      onChange={(e) => setUnidadFisica(e.target.value)}
                      className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-[10px] font-semibold text-gray-500 uppercase">Descripción General de Cualidades, Material o Tamaño</label>
                  <textarea
                    rows={3}
                    required
                    placeholder="E.g. Fabricado en acero de alto carbono, mangos con recubrimiento dieléctrico, estuche organizador rígido protector..."
                    value={descripcionGeneral}
                    onChange={(e) => setDescripcionGeneral(e.target.value)}
                    className="w-full bg-slate-50 border border-gray-300 rounded p-2.5 text-xs focus:bg-white mt-1 font-sans"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[10px] font-semibold text-gray-500 uppercase">Marca / Fabricante Ofrecido</label>
                    <input
                      type="text"
                      required
                      placeholder="E.g. Stanley / Bosch / Facom"
                      value={marcaOfrecida}
                      onChange={(e) => setMarcaOfrecida(e.target.value)}
                      className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-semibold text-gray-500 uppercase">Nombre de Quien Firma la Propuesta</label>
                    <input
                      type="text"
                      required
                      value={firmaProponente}
                      onChange={(e) => setFirmaProponente(e.target.value)}
                      className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                    />
                  </div>
                </div>
              </div>

              {/* Modal Footer actions */}
              <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => setSelectedItemForQuote(null)}
                  className="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg text-xs font-medium text-gray-700"
                >
                  Cancelar
                </button>
                <button
                  id="btn-save-quote-submit"
                  type="submit"
                  className="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-bold shadow"
                >
                  Registrar Cotización y Ficha Técnica
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

    </div>
  );
}
