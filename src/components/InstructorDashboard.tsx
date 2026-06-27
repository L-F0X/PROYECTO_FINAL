import React, { useState } from "react";
import { 
  Plus, 
  FileText, 
  CheckCircle, 
  Send, 
  Trash, 
  Edit3, 
  Users, 
  Tag, 
  TrendingUp, 
  AlertCircle,
  HelpCircle,
  Hash
} from "lucide-react";
import { 
  LoteRequerimiento, 
  MatrizItem, 
  Necesidad, 
  CodigoUnspsc, 
  Usuario 
} from "../types";

interface InstructorDashboardProps {
  usuarios: Usuario[];
  lotes: LoteRequerimiento[];
  matrizItems: MatrizItem[];
  necesidades: Necesidad[];
  codigosUnspsc: CodigoUnspsc[];
  onUpdateLotes: (updated: LoteRequerimiento[]) => void;
  onUpdateMatrizItems: (updated: MatrizItem[]) => void;
  onUpdateNecesidades: (updated: Necesidad[]) => void;
  onUpdateCodigosUnspsc: (updated: CodigoUnspsc[]) => void;
}

export default function InstructorDashboard({
  usuarios,
  lotes,
  matrizItems,
  necesidades,
  codigosUnspsc,
  onUpdateLotes,
  onUpdateMatrizItems,
  onUpdateNecesidades,
  onUpdateCodigosUnspsc
}: InstructorDashboardProps) {
  const [selectedLoteId, setSelectedLoteId] = useState<number | null>(lotes[0]?.ID_LOTE || null);
  const [isCreatingLote, setIsCreatingLote] = useState(false);
  const [newLoteNombre, setNewLoteNombre] = useState("");
  const [instructorApoyoId, setInstructorApoyoId] = useState<number>(102);

  // Form states for adding a new Item to the Selected Lot
  const [descripcionBien, setDescripcionBien] = useState("");
  const [unidadMedida, setUnidadMedida] = useState("Unidad");
  const [cantidadRegular, setCantidadRegular] = useState<number>(10);
  const [oferta1, setOferta1] = useState<number>(0);
  const [oferta2, setOferta2] = useState<number>(0);
  const [oferta3, setOferta3] = useState<number>(0);

  // Form states for UNSPSC code
  const [segmentoCode, setSegmentoCode] = useState("");
  const [familiaCode, setFamiliaCode] = useState("");
  const [claseCode, setClaseCode] = useState("");

  // Need distribution modal
  const [selectedItemForNeed, setSelectedItemForNeed] = useState<MatrizItem | null>(null);
  const [needForm, setNeedForm] = useState<Record<string, number>>({
    CANTIDAD_REGULAR: 0,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 0,
    CANTIDAD_MEDIA_TECNICA: 0,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0
  });

  const instructors = usuarios.filter((u) => u.ID_ROL === 1);

  const selectedLote = lotes.find((l) => l.ID_LOTE === selectedLoteId);
  const filteredItems = matrizItems.filter((item) => item.ID_LOTE === selectedLoteId);

  // Create a new Requirement Lot
  const handleCreateLote = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newLoteNombre.trim()) return;

    const newLote: LoteRequerimiento = {
      ID_LOTE: Date.now(),
      ID_SOLICITANTE: 101, // Carlos Gómez
      ID_INSTRUCTOR_APOYO: Number(instructorApoyoId),
      LOTE_NOMBRE: newLoteNombre,
      ESTADO_TRAMITE: "BORRADOR",
      FECHA_CREACIÓN: new Date().toISOString().split("T")[0]
    };

    onUpdateLotes([newLote, ...lotes]);
    setSelectedLoteId(newLote.ID_LOTE);
    setNewLoteNombre("");
    setIsCreatingLote(false);
  };

  // Add Item to Matriz
  const handleAddItem = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedLoteId || !descripcionBien.trim()) return;

    const itemId = Date.now();
    const qty = Number(cantidadRegular);

    // Calculate initial average
    const val1 = Number(oferta1) || 0;
    const val2 = Number(oferta2) || 0;
    const val3 = Number(oferta3) || 0;
    const activeOffers = [val1, val2, val3].filter(v => v > 0);
    const avg = activeOffers.length > 0 
      ? Math.round(activeOffers.reduce((a, b) => a + b, 0) / activeOffers.length) 
      : 0;

    const newItem: MatrizItem = {
      ID_MATRIZ_ITEM: itemId,
      ID_LOTE: selectedLoteId,
      DESCRIPCIÓN_BIEN: descripcionBien,
      UNIDAD_MEDIDA: unidadMedida,
      CANTIDAD_REGULAR: qty,
      OFERTA_1: val1,
      OFERTA_2: val2,
      OFERTA_3: val3,
      VALOR_UNITARIO_PROMEDIO: avg,
      VALOR_TORAL_PROMEDIO: avg * qty
    };

    // Auto-create code UNSPSC structure
    const newCode: CodigoUnspsc = {
      ID_CODIGO: itemId + 1,
      ID_MATRIZ_ITEM: itemId,
      SEGMENTO: segmentoCode.trim() || "N/A",
      FAMILIA: familiaCode.trim() || "N/A",
      CLASE: claseCode.trim() || "N/A"
    };

    // Auto-create basic regular need mapping
    const newNeed: Necesidad = {
      ID_NECESIDAD: itemId + 2,
      ID_MATRIZ: itemId,
      CANTIDAD_REGULAR: qty,
      CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
      CANTIDAD_CAMPESINA_TITULADA: 0,
      CANTIDAD_VULNERABLE: 0,
      CANTIDAD_MEDIA_TECNICA: 0,
      CANTIDAD_FIC: 0,
      CANTIDAD_ECONOMIA_POPULAR: 0,
      CANTIDAD_ENI: 0,
      CANTIDAD_FC_CAMPESINA: 0,
      CANTIDAD_NESECIDAD: qty
    };

    onUpdateMatrizItems([...matrizItems, newItem]);
    onUpdateCodigosUnspsc([...codigosUnspsc, newCode]);
    onUpdateNecesidades([...necesidades, newNeed]);

    // Reset Form
    setDescripcionBien("");
    setCantidadRegular(10);
    setOferta1(0);
    setOferta2(0);
    setOferta3(0);
    setSegmentoCode("");
    setFamiliaCode("");
    setClaseCode("");
  };

  // Remove item
  const handleRemoveItem = (itemId: number) => {
    onUpdateMatrizItems(matrizItems.filter((item) => item.ID_MATRIZ_ITEM !== itemId));
    onUpdateNecesidades(necesidades.filter((n) => n.ID_MATRIZ !== itemId));
    onUpdateCodigosUnspsc(codigosUnspsc.filter((c) => c.ID_MATRIZ_ITEM !== itemId));
  };

  // Open Population Needs modal
  const openNeedModal = (item: MatrizItem) => {
    const existing = necesidades.find((n) => n.ID_MATRIZ === item.ID_MATRIZ_ITEM);
    setSelectedItemForNeed(item);
    if (existing) {
      setNeedForm({
        CANTIDAD_REGULAR: existing.CANTIDAD_REGULAR,
        CANTIDAD_CAMPESINA_COMPLEMENTARIA: existing.CANTIDAD_CAMPESINA_COMPLEMENTARIA,
        CANTIDAD_CAMPESINA_TITULADA: existing.CANTIDAD_CAMPESINA_TITULADA,
        CANTIDAD_VULNERABLE: existing.CANTIDAD_VULNERABLE,
        CANTIDAD_MEDIA_TECNICA: existing.CANTIDAD_MEDIA_TECNICA,
        CANTIDAD_FIC: existing.CANTIDAD_FIC,
        CANTIDAD_ECONOMIA_POPULAR: existing.CANTIDAD_ECONOMIA_POPULAR,
        CANTIDAD_ENI: existing.CANTIDAD_ENI,
        CANTIDAD_FC_CAMPESINA: existing.CANTIDAD_FC_CAMPESINA
      });
    } else {
      setNeedForm({
        CANTIDAD_REGULAR: item.CANTIDAD_REGULAR,
        CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
        CANTIDAD_CAMPESINA_TITULADA: 0,
        CANTIDAD_VULNERABLE: 0,
        CANTIDAD_MEDIA_TECNICA: 0,
        CANTIDAD_FIC: 0,
        CANTIDAD_ECONOMIA_POPULAR: 0,
        CANTIDAD_ENI: 0,
        CANTIDAD_FC_CAMPESINA: 0
      });
    }
  };

  // Save Needs distribution
  const saveNeedForm = () => {
    if (!selectedItemForNeed) return;

    const totalCalculated = 
      Number(needForm.CANTIDAD_REGULAR || 0) +
      Number(needForm.CANTIDAD_CAMPESINA_COMPLEMENTARIA || 0) +
      Number(needForm.CANTIDAD_CAMPESINA_TITULADA || 0) +
      Number(needForm.CANTIDAD_VULNERABLE || 0) +
      Number(needForm.CANTIDAD_MEDIA_TECNICA || 0) +
      Number(needForm.CANTIDAD_FIC || 0) +
      Number(needForm.CANTIDAD_ECONOMIA_POPULAR || 0) +
      Number(needForm.CANTIDAD_ENI || 0) +
      Number(needForm.CANTIDAD_FC_CAMPESINA || 0);

    // Update Necesiades list
    const updated = necesidades.map((n) => {
      if (n.ID_MATRIZ === selectedItemForNeed.ID_MATRIZ_ITEM) {
        return {
          ...n,
          ...needForm,
          CANTIDAD_NESECIDAD: totalCalculated
        };
      }
      return n;
    });

    onUpdateNecesidades(updated);

    // Also update quantity in MatrizItem to match total calculated need
    const updatedMatriz = matrizItems.map((item) => {
      if (item.ID_MATRIZ_ITEM === selectedItemForNeed.ID_MATRIZ_ITEM) {
        return {
          ...item,
          CANTIDAD_REGULAR: totalCalculated,
          VALOR_TORAL_PROMEDIO: item.VALOR_UNITARIO_PROMEDIO * totalCalculated
        };
      }
      return item;
    });

    onUpdateMatrizItems(updatedMatriz);
    setSelectedItemForNeed(null);
  };

  // Submit Lot to Coordinator
  const handleSubmitLot = () => {
    if (!selectedLoteId) return;

    const updated = lotes.map((l) => {
      if (l.ID_LOTE === selectedLoteId) {
        return {
          ...l,
          ESTADO_TRAMITE: "ENVIADO_A_COORDINADOR" as const
        };
      }
      return l;
    });
    onUpdateLotes(updated);
  };

  const calculateLotTotal = () => {
    return filteredItems.reduce((sum, item) => sum + item.VALOR_TORAL_PROMEDIO, 0);
  };

  return (
    <div id="instructor-workspace" className="space-y-6">
      {/* Top action header */}
      <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-150">
          <div>
            <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
              <Users className="h-5 w-5 text-emerald-600" />
              Portal del Instructor Líder de Formación
            </h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Genera solicitudes de materiales de pre-compra, detalla la matriz de ítems, y justifica las necesidades por tipo de población del SENA.
            </p>
          </div>
          <button
            id="btn-create-lote"
            onClick={() => setIsCreatingLote(true)}
            className="inline-flex items-center gap-2 bg-[#39A900] hover:bg-[#2e8800] text-white font-medium px-4 py-2.5 rounded-lg text-sm transition-colors shadow-sm self-start lg:self-auto cursor-pointer"
          >
            <Plus className="h-4 w-4" /> Nuevo Lote de Requerimiento
          </button>
        </div>

        {/* Acciones e Instrucciones de Rol */}
        <div className="mt-6">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Acciones y Guía del Instructor</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div className="p-3.5 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">1. Crear Lotes</span>
              <p className="text-[10px] text-slate-500 mt-1">Crea paquetes de materiales organizados según el centro de formación o programa académico.</p>
            </div>
            <div className="p-3.5 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">2. Planificar Matriz</span>
              <p className="text-[10px] text-slate-500 mt-1">Registra los bienes de consumo con descripción, unidad y ofertas comerciales de referencia.</p>
            </div>
            <div className="p-3.5 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">3. Códigos UNSPSC</span>
              <p className="text-[10px] text-slate-500 mt-1">Clasifica técnicamente cada ítem con los segmentos de compras de las Naciones Unidas.</p>
            </div>
            <div className="p-3.5 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">4. Distribuir Poblaciones</span>
              <p className="text-[10px] text-slate-500 mt-1">Segmenta las unidades físicas requeridas entre programas regulares, FIC, rural o vulnerables.</p>
            </div>
            <div className="p-3.5 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">5. Radicar Trámite</span>
              <p className="text-[10px] text-slate-500 mt-1">Envía la solicitud completa al coordinador para que emita viabilidad y dictamen oficial.</p>
            </div>
          </div>
        </div>

        {/* Create Lote form */}
        {isCreatingLote && (
          <form id="form-create-lote" onSubmit={handleCreateLote} className="mt-6 p-4 bg-slate-50 rounded-xl border border-gray-200 animate-fadeIn space-y-4">
            <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider">Crear Nuevo Trámite de Pre-Compra</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Nombre Descriptivo del Lote</label>
                <input
                  type="text"
                  required
                  placeholder="Ej: Materiales Eléctricos para Automatización T3"
                  value={newLoteNombre}
                  onChange={(e) => setNewLoteNombre(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-emerald-500"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Instructor de Apoyo Solicitante</label>
                <select
                  value={instructorApoyoId}
                  onChange={(e) => setInstructorApoyoId(Number(e.target.value))}
                  className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-emerald-500"
                >
                  {instructors.filter(u => u.ID_USUARIO !== 101).map((u) => (
                    <option key={u.ID_USUARIO} value={u.ID_USUARIO}>
                      {u.NOMBRE} {u.APELLIDO} ({u.EMAIL})
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setIsCreatingLote(false)}
                className="px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100 rounded-lg font-medium"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="px-4 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-sm"
              >
                Crear Lote
              </button>
            </div>
          </form>
        )}

        {/* Lote selector tabs */}
        <div className="mt-6">
          <label className="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Trámites en Curso</label>
          <div className="flex gap-2 overflow-x-auto pb-2">
            {lotes.map((lote) => {
              const count = matrizItems.filter((i) => i.ID_LOTE === lote.ID_LOTE).length;
              return (
                <button
                  key={lote.ID_LOTE}
                  id={`tab-lote-${lote.ID_LOTE}`}
                  onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                  className={`px-4 py-3 rounded-lg border text-left transition-all flex-shrink-0 flex flex-col justify-between ${
                    selectedLoteId === lote.ID_LOTE
                      ? "bg-emerald-50 border-emerald-500 text-emerald-950 ring-1 ring-emerald-500"
                      : "bg-gray-50 hover:bg-gray-100 border-gray-200 text-gray-600"
                  }`}
                >
                  <span className="font-bold text-sm line-clamp-1 max-w-[220px]">{lote.LOTE_NOMBRE}</span>
                  <div className="flex items-center justify-between gap-4 mt-2">
                    <span className="text-[10px] font-semibold bg-white border border-gray-200 px-1.5 py-0.5 rounded text-gray-500">
                      {count} {count === 1 ? "ítem" : "ítems"}
                    </span>
                    <span className={`text-[9px] font-black px-1.5 py-0.5 rounded-full ${
                      lote.ESTADO_TRAMITE === "BORRADOR" ? "bg-amber-100 text-amber-800" :
                      lote.ESTADO_TRAMITE === "ENVIADO_A_COORDINADOR" ? "bg-blue-100 text-blue-800" :
                      lote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" ? "bg-red-100 text-red-800" :
                      lote.ESTADO_TRAMITE === "APROBADO_COORDINADOR" ? "bg-purple-100 text-purple-800" :
                      "bg-emerald-100 text-emerald-800"
                    }`}>
                      {lote.ESTADO_TRAMITE.replace(/_/g, " ")}
                    </span>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      </div>

      {selectedLote && (
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          {/* Items matrix panel */}
          <div className="xl:col-span-2 space-y-6">
            <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
              <div className="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <div>
                  <h3 className="font-bold text-gray-800 flex items-center gap-1.5">
                    <FileText className="h-5 w-5 text-emerald-600" />
                    Matriz de Bienes en el Lote
                  </h3>
                  <p className="text-xs text-gray-400">Listado consolidado de materiales y herramientas de formación.</p>
                </div>
                <div className="text-right">
                  <span className="text-xs text-gray-400 block font-semibold">Presupuesto Estimado Promedio</span>
                  <span className="text-lg font-black text-emerald-700">${calculateLotTotal().toLocaleString()} COP</span>
                </div>
              </div>

              {/* Items List Table */}
              {filteredItems.length === 0 ? (
                <div className="text-center py-12 bg-slate-50 rounded-xl border border-dashed border-gray-200">
                  <AlertCircle className="h-10 w-10 text-gray-300 mx-auto mb-2" />
                  <p className="text-sm font-semibold text-gray-500">No hay materiales registrados en este lote</p>
                  <p className="text-xs text-gray-400 mt-1">Usa el formulario inferior para agregar el primer ítem.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-gray-100 text-gray-400 text-xs font-semibold uppercase font-mono">
                        <th className="py-2.5 px-2">Descripción</th>
                        <th className="py-2.5 px-2">Unidad</th>
                        <th className="py-2.5 px-2 text-right">Cant. Total</th>
                        <th className="py-2.5 px-2 text-right">Est. Prom Unit</th>
                        <th className="py-2.5 px-2 text-right">Est. Prom Total</th>
                        <th className="py-2.5 px-2 text-center">Clasificación UNSPSC</th>
                        <th className="py-2.5 px-2 text-center">Acciones</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-xs">
                      {filteredItems.map((item) => {
                        const code = codigosUnspsc.find((c) => c.ID_MATRIZ_ITEM === item.ID_MATRIZ_ITEM);
                        const need = necesidades.find((n) => n.ID_MATRIZ === item.ID_MATRIZ_ITEM);
                        return (
                          <tr key={item.ID_MATRIZ_ITEM} className="hover:bg-slate-50/50">
                            <td className="py-3 px-2">
                              <span className="font-bold text-gray-800 block text-sm">{item.DESCRIPCIÓN_BIEN}</span>
                              <span className="text-[10px] text-gray-400 font-mono">ID_MATRIZ_ITEM: {item.ID_MATRIZ_ITEM}</span>
                            </td>
                            <td className="py-3 px-2 text-gray-600 font-semibold">{item.UNIDAD_MEDIDA}</td>
                            <td className="py-3 px-2 text-right font-bold text-gray-700 text-sm">
                              {item.CANTIDAD_REGULAR}
                            </td>
                            <td className="py-3 px-2 text-right text-gray-600 font-semibold">
                              ${item.VALOR_UNITARIO_PROMEDIO.toLocaleString()}
                            </td>
                            <td className="py-3 px-2 text-right text-emerald-800 font-bold">
                              ${item.VALOR_TORAL_PROMEDIO.toLocaleString()}
                            </td>
                            <td className="py-3 px-2 text-center">
                              {code ? (
                                <div className="inline-block text-[10px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono text-left max-w-[150px] truncate" title={`${code.SEGMENTO} - ${code.FAMILIA} - ${code.CLASE}`}>
                                  {code.CLASE.split(" ")[0] || "No codificado"}
                                </div>
                              ) : (
                                <span className="text-red-500 font-semibold">Falta Código</span>
                              )}
                            </td>
                            <td className="py-3 px-2">
                              <div className="flex items-center justify-center gap-1.5">
                                <button
                                  id={`btn-need-${item.ID_MATRIZ_ITEM}`}
                                  onClick={() => openNeedModal(item)}
                                  className="inline-flex items-center gap-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2 py-1 rounded font-bold text-[10px] border border-emerald-200 transition-colors"
                                  title="Detalle de Necesidades por población"
                                >
                                  <Users className="w-3 w-3" /> Necesidades
                                </button>
                                {selectedLote.ESTADO_TRAMITE === "BORRADOR" && (
                                  <button
                                    id={`btn-remove-item-${item.ID_MATRIZ_ITEM}`}
                                    onClick={() => handleRemoveItem(item.ID_MATRIZ_ITEM)}
                                    className="p-1 text-red-500 hover:bg-red-50 hover:text-red-700 rounded transition-colors"
                                    title="Eliminar Ítem"
                                  >
                                    <Trash className="w-3.5 h-3.5" />
                                  </button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}

              {/* LOT SUBMISSION BOX */}
              <div className="mt-6 pt-6 border-t border-gray-100 flex items-center justify-between bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                <div className="flex items-start gap-2.5 max-w-lg">
                  <CheckCircle className="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" />
                  <div>
                    <h4 className="text-sm font-bold text-emerald-950">Envío del Trámite para Aprobación</h4>
                    {selectedLote.ESTADO_TRAMITE === "BORRADOR" || selectedLote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" ? (
                      <p className="text-xs text-emerald-800 mt-0.5">
                        Una vez finalizado el listado de ítems, sus códigos UNSPSC y la justificación de necesidades, proceda a enviar el lote a la Coordinación Académica.
                      </p>
                    ) : (
                      <p className="text-xs text-slate-500 mt-0.5">
                        Este trámite ya fue enviado y se encuentra en estado <strong className="font-bold uppercase text-emerald-700">{selectedLote.ESTADO_TRAMITE.replace(/_/g, " ")}</strong>.
                      </p>
                    )}
                  </div>
                </div>
                {(selectedLote.ESTADO_TRAMITE === "BORRADOR" || selectedLote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR") && (
                  <button
                    id="btn-submit-lote-coord"
                    onClick={handleSubmitLot}
                    disabled={filteredItems.length === 0}
                    className="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold px-4 py-2 rounded-lg text-sm transition-all shadow-sm"
                  >
                    <Send className="w-4 h-4" /> Enviar a Coordinador
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Sidebar adding items / comments info */}
          <div className="space-y-6">
            {/* If there are coordinator comments */}
            {selectedLote.COMENTARIOS_COORDINADOR && (
              <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                <h4 className="text-sm font-bold text-red-950 flex items-center gap-1.5">
                  <AlertCircle className="w-4 h-4 text-red-600" />
                  Observaciones de la Coordinación
                </h4>
                <p className="text-xs text-red-800 mt-1 bg-white p-2.5 rounded border border-red-100">
                  "{selectedLote.COMENTARIOS_COORDINADOR}"
                </p>
              </div>
            )}

            {/* Form to Add Item to Matriz */}
            {selectedLote.ESTADO_TRAMITE === "BORRADOR" || selectedLote.ESTADO_TRAMITE === "RECHAZADO_COORDINADOR" ? (
              <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
                <h3 className="font-bold text-gray-800 flex items-center gap-1.5 mb-4 border-b border-gray-100 pb-2">
                  <Plus className="h-5 w-5 text-emerald-600" />
                  Agregar Material a la Matriz
                </h3>
                <form id="form-add-item" onSubmit={handleAddItem} className="space-y-4">
                  <div>
                    <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Descripción Técnica del Bien</label>
                    <textarea
                      required
                      rows={2}
                      placeholder="Ej: Cable UTP Categoría 6 LSZH 100% Cobre, color azul"
                      value={descripcionBien}
                      onChange={(e) => setDescripcionBien(e.target.value)}
                      className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-emerald-500 font-sans"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Unidad de Medida</label>
                      <select
                        value={unidadMedida}
                        onChange={(e) => setUnidadMedida(e.target.value)}
                        className="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-emerald-500"
                      >
                        <option value="Unidad">Unidad</option>
                        <option value="Kilogramos">Kilogramos</option>
                        <option value="Metros">Metros</option>
                        <option value="Caja x 100">Caja x 100</option>
                        <option value="Galón">Galón</option>
                        <option value="Licencia">Licencia</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Cantidad Sugerida</label>
                      <input
                        type="number"
                        min="1"
                        required
                        value={cantidadRegular}
                        onChange={(e) => setCantidadRegular(Number(e.target.value))}
                        className="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-emerald-500 font-semibold"
                      />
                    </div>
                  </div>

                  {/* Pricing Offers for Study */}
                  <div className="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-2">
                    <span className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1">
                      <TrendingUp className="w-3 w-3" /> Precios de Cotizaciones de Referencia (Estudio)
                    </span>
                    <div className="grid grid-cols-3 gap-1.5">
                      <div>
                        <label className="block text-[9px] font-semibold text-gray-500">Oferta 1</label>
                        <input
                          type="number"
                          placeholder="COP $"
                          value={oferta1 || ""}
                          onChange={(e) => setOferta1(Number(e.target.value))}
                          className="w-full bg-white border border-gray-300 rounded px-1.5 py-1 text-xs"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] font-semibold text-gray-500">Oferta 2</label>
                        <input
                          type="number"
                          placeholder="COP $"
                          value={oferta2 || ""}
                          onChange={(e) => setOferta2(Number(e.target.value))}
                          className="w-full bg-white border border-gray-300 rounded px-1.5 py-1 text-xs"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] font-semibold text-gray-500">Oferta 3</label>
                        <input
                          type="number"
                          placeholder="COP $"
                          value={oferta3 || ""}
                          onChange={(e) => setOferta3(Number(e.target.value))}
                          className="w-full bg-white border border-gray-300 rounded px-1.5 py-1 text-xs"
                        />
                      </div>
                    </div>
                  </div>

                  {/* UNSPSC Classification */}
                  <div className="bg-emerald-50/40 p-3 rounded-lg border border-emerald-100 space-y-2">
                    <span className="block text-[10px] font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-1">
                      <Tag className="w-3 w-3" /> Clasificación UNSPSC (Naciones Unidas)
                    </span>
                    <div className="space-y-1.5">
                      <div>
                        <label className="block text-[9px] text-gray-500">Segmento</label>
                        <input
                          type="text"
                          placeholder="E.g. 43 (Tecnología de la Información)"
                          value={segmentoCode}
                          onChange={(e) => setSegmentoCode(e.target.value)}
                          className="w-full bg-white border border-gray-300 rounded px-2 py-1 text-xs"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] text-gray-500">Familia</label>
                        <input
                          type="text"
                          placeholder="E.g. 4323 (Software)"
                          value={familiaCode}
                          onChange={(e) => setFamiliaCode(e.target.value)}
                          className="w-full bg-white border border-gray-300 rounded px-2 py-1 text-xs"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] text-gray-500">Clase / Código Final</label>
                        <input
                          type="text"
                          placeholder="E.g. 432321 (Software CAD)"
                          value={claseCode}
                          onChange={(e) => setClaseCode(e.target.value)}
                          className="w-full bg-white border border-gray-300 rounded px-2 py-1 text-xs"
                        />
                      </div>
                    </div>
                  </div>

                  <button
                    id="btn-add-item-to-list"
                    type="submit"
                    className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg text-xs transition-colors shadow-sm"
                  >
                    Agregar a la Matriz
                  </button>
                </form>
              </div>
            ) : (
              <div className="bg-slate-100 border border-slate-200 rounded-xl p-5 text-center text-slate-500">
                <AlertCircle className="w-8 h-8 text-slate-400 mx-auto mb-2" />
                <h4 className="text-sm font-bold text-slate-700">Edición Deshabilitada</h4>
                <p className="text-xs text-slate-500 mt-1">
                  El lote no se encuentra en estado Borrador o Rechazado, por lo que no es posible agregar o modificar materiales en este momento.
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Population Needs Distribution Modal */}
      {selectedItemForNeed && (
        <div id="modal-necesidad" className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-2xl overflow-hidden animate-scaleIn">
            {/* Modal Header */}
            <div className="bg-emerald-950 text-white p-5 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">
                  NECESIDAD INSTITUCIONAL (SENA)
                </span>
                <h3 className="text-lg font-black mt-0.5">{selectedItemForNeed.DESCRIPCIÓN_BIEN}</h3>
              </div>
              <button 
                onClick={() => setSelectedItemForNeed(null)}
                className="text-white hover:text-red-200 font-bold text-lg"
              >
                ✕
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 max-h-[480px] overflow-y-auto space-y-4">
              <p className="text-xs text-gray-500">
                Justifica cuantitativamente la solicitud de este bien. Distribuye la cantidad total entre los diferentes programas y poblaciones beneficiarias del Centro de Formación. El total acumulado actualizará automáticamente el ítem en la matriz.
              </p>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Regular Training */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">1. Formación Regular (Presencial/Virtual)</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_REGULAR}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_REGULAR: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* CampeSENA Complementaria */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">2. CampeSENA Complementaria</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_CAMPESINA_COMPLEMENTARIA}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_CAMPESINA_COMPLEMENTARIA: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* CampeSENA Titulada */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">3. CampeSENA Titulada</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_CAMPESINA_TITULADA}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_CAMPESINA_TITULADA: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* Vulnerables */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">4. Poblaciones Vulnerables</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_VULNERABLE}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_VULNERABLE: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* Articulacion Media Tecnica */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">5. Articulación Media Técnica</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_MEDIA_TECNICA}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_MEDIA_TECNICA: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* FIC */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">6. Fondo Industria Construcción (FIC)</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_FIC}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_FIC: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* Economia Popular */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">7. Economía Popular</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_ECONOMIA_POPULAR}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_ECONOMIA_POPULAR: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* ENI */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">8. Innovación (ENI)</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_ENI}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_ENI: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>

                {/* Formacion Campo Campesina */}
                <div>
                  <label className="block text-xs font-semibold text-gray-700">9. Formación en Campo Campesina</label>
                  <input
                    type="number"
                    min="0"
                    value={needForm.CANTIDAD_FC_CAMPESINA}
                    onChange={(e) => setNeedForm({ ...needForm, CANTIDAD_FC_CAMPESINA: Number(e.target.value) })}
                    className="w-full bg-slate-50 border border-gray-300 rounded px-2.5 py-1.5 text-xs focus:bg-white mt-1"
                  />
                </div>
              </div>

              {/* Total counter */}
              <div className="bg-emerald-50 rounded-lg p-3 border border-emerald-200 flex items-center justify-between font-mono font-bold text-emerald-950 mt-4">
                <span>CANTIDAD_NESECIDAD (SUMA ACUMULADA):</span>
                <span className="text-base text-emerald-800">
                  {Number(needForm.CANTIDAD_REGULAR || 0) +
                   Number(needForm.CANTIDAD_CAMPESINA_COMPLEMENTARIA || 0) +
                   Number(needForm.CANTIDAD_CAMPESINA_TITULADA || 0) +
                   Number(needForm.CANTIDAD_VULNERABLE || 0) +
                   Number(needForm.CANTIDAD_MEDIA_TECNICA || 0) +
                   Number(needForm.CANTIDAD_FIC || 0) +
                   Number(needForm.CANTIDAD_ECONOMIA_POPULAR || 0) +
                   Number(needForm.CANTIDAD_ENI || 0) +
                   Number(needForm.CANTIDAD_FC_CAMPESINA || 0)} {selectedItemForNeed.UNIDAD_MEDIDA}
                </span>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="bg-slate-50 border-t border-gray-150 p-4 flex justify-end gap-2">
              <button
                onClick={() => setSelectedItemForNeed(null)}
                className="px-4 py-2 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg text-xs font-medium text-gray-700"
              >
                Cerrar
              </button>
              <button
                id="btn-save-need-form"
                onClick={saveNeedForm}
                className="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-bold shadow"
              >
                Guardar y Sincronizar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
