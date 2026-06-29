import React, { useState } from "react";
import { 
  ShieldCheck, 
  FileCheck, 
  Search, 
  AlertTriangle, 
  Calendar, 
  User, 
  FileText,
  Clock
} from "lucide-react";
import { 
  LoteRequerimiento, 
  MatrizItem, 
  CertificadoExistencia, 
  Usuario 
} from "../types";

interface StorekeeperDashboardProps {
  usuarios: Usuario[];
  lotes: LoteRequerimiento[];
  matrizItems: MatrizItem[];
  certificados: CertificadoExistencia[];
  onUpdateLotes: (updated: LoteRequerimiento[]) => void;
  onUpdateCertificados: (updated: CertificadoExistencia[]) => void;
}

export default function StorekeeperDashboard({
  usuarios,
  lotes,
  matrizItems,
  certificados,
  onUpdateLotes,
  onUpdateCertificados
}: StorekeeperDashboardProps) {
  const [selectedLoteId, setSelectedLoteId] = useState<number | null>(
    lotes.find(l => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR")?.ID_LOTE || lotes[0]?.ID_LOTE || null
  );

  // Form state for generating a certificate of non-existence
  const [certificadoNum, setCertificadoNum] = useState("");
  const [comentariosAlmacen, setComentariosAlmacen] = useState("");

  const selectedLote = lotes.find((l) => l.ID_LOTE === selectedLoteId);
  const filteredItems = matrizItems.filter((i) => i.ID_LOTE === selectedLoteId);
  const associatedCert = certificados.find((c) => c.ID_LOTE === selectedLoteId);

  // Lotes pending certificate of non-existence
  const pendingLots = lotes.filter((l) => l.ESTADO_TRAMITE === "APROBADO_COORDINADOR");
  const completedLots = lotes.filter((l) => 
    l.ESTADO_TRAMITE === "CON_CERTIFICADO_NO_EXISTENCIA" || 
    l.ESTADO_TRAMITE === "COTIZADO" || 
    l.ESTADO_TRAMITE === "PROCESADO"
  );

  const handleGenerateCertificate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedLoteId || !certificadoNum.trim() || !comentariosAlmacen.trim()) return;

    // Create Certificado record
    const newCert: CertificadoExistencia = {
      ID_CERTIFICADO: Date.now(),
      NUMERO_CERTIFICADO: certificadoNum.trim(),
      ID_LOTE: selectedLoteId,
      COMENTARIOS: comentariosAlmacen.trim(),
      FECHA_EMISIÓN: new Date().toISOString()
    };

    // Update lot state
    const updatedLotes = lotes.map((l) => {
      if (l.ID_LOTE === selectedLoteId) {
        return {
          ...l,
          ESTADO_TRAMITE: "CON_CERTIFICADO_NO_EXISTENCIA" as const
        };
      }
      return l;
    });

    onUpdateCertificados([...certificados, newCert]);
    onUpdateLotes(updatedLotes);

    // Reset Form
    setCertificadoNum("");
    setComentariosAlmacen("");
  };

  // Generate automated code helper
  const triggerAutoCode = () => {
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    setCertificadoNum(`CNE-2026-${randomNum}`);
    setComentariosAlmacen("Se revisó detalladamente el sistema de inventario interno del SENA Sofia Plus y las bodegas físicas de almacenamiento. Confirmamos que las especificaciones requeridas no se encuentran disponibles en stock. Procede la pre-compra.");
  };

  return (
    <div id="storekeeper-workspace" className="space-y-6">
      {/* Overview header */}
      <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
        <div className="flex items-center gap-3 pb-5 border-b border-slate-100">
          <div className="p-3 bg-emerald-50 text-emerald-700 rounded-lg">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-gray-900">Portal del Almacenista General</h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Inspecciona los materiales aprobados y expide el Certificado de No Existencia en inventario físico para autorizar los estudios de mercado públicos.
            </p>
          </div>
        </div>

        {/* Acciones e Instrucciones de Rol */}
        <div className="mt-5">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Acciones y Guía del Almacenista</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">1. Inspección Física</span>
              <p className="text-[10px] text-slate-500 mt-1">Busca y valida en las bodegas reales si los ítems solicitados por el instructor ya se encuentran en inventario.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">2. Certificado de No Existencia</span>
              <p className="text-[10px] text-slate-500 mt-1">Expide el documento obligatorio exigido por los entes de control para habilitar el presupuesto público.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">3. Radicar y Numerar</span>
              <p className="text-[10px] text-slate-500 mt-1">Genera un código oficial consecutivo de control interno que asocia las firmas del centro.</p>
            </div>
            <div className="p-3 bg-slate-50/50 border border-slate-150 rounded-xl">
              <span className="text-xs font-bold text-slate-700 block">4. Autorizar Cotización</span>
              <p className="text-[10px] text-slate-500 mt-1">Publica el requerimiento, permitiendo a los proponentes del mercado ofertar precios unitarios oficiales.</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left sidebar: list of lots */}
        <div className="lg:col-span-1 space-y-4">
          {/* Pending lots */}
          <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-4">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1">
              <Clock className="w-3.5 h-3.5 text-amber-500" />
              Por Certificar ({pendingLots.length})
            </h3>
            {pendingLots.length === 0 ? (
              <div className="p-4 text-center bg-emerald-50/50 border border-emerald-100 rounded-lg text-emerald-800 text-xs font-medium">
                ¡Al día! No hay lotes pendientes de stock.
              </div>
            ) : (
              <div className="space-y-2">
                {pendingLots.map((lote) => {
                  const itemsCount = matrizItems.filter((i) => i.ID_LOTE === lote.ID_LOTE).length;
                  const instructor = usuarios.find((u) => u.ID_USUARIO === lote.ID_SOLICITANTE);
                  return (
                    <button
                      key={lote.ID_LOTE}
                      id={`store-pending-lote-${lote.ID_LOTE}`}
                      onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                      className={`w-full text-left p-3 rounded-lg border transition-all ${
                        selectedLoteId === lote.ID_LOTE
                          ? "bg-emerald-50 border-emerald-500 text-emerald-950 shadow-xs"
                          : "bg-slate-50 border-slate-200 hover:bg-slate-100 text-gray-700"
                      }`}
                    >
                      <h4 className="font-bold text-sm line-clamp-1">{lote.LOTE_NOMBRE}</h4>
                      <p className="text-[10px] text-gray-500 mt-1">Instructor: {instructor?.NOMBRE} {instructor?.APELLIDO}</p>
                      <div className="flex justify-between items-center mt-2 text-[10px]">
                        <span className="text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">
                          Aprobado Coord.
                        </span>
                        <span className="font-mono text-gray-400 font-bold">{itemsCount} ítems</span>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Certified lots */}
          <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-4">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Certificados Expedidos ({completedLots.length})</h3>
            <div className="space-y-2 max-h-[250px] overflow-y-auto">
              {completedLots.map((lote) => {
                const cert = certificados.find((c) => c.ID_LOTE === lote.ID_LOTE);
                return (
                  <button
                    key={lote.ID_LOTE}
                    id={`store-completed-lote-${lote.ID_LOTE}`}
                    onClick={() => setSelectedLoteId(lote.ID_LOTE)}
                    className={`w-full text-left p-3 rounded-lg border transition-all text-xs flex flex-col ${
                      selectedLoteId === lote.ID_LOTE
                        ? "bg-slate-100 border-slate-400 text-slate-950"
                        : "bg-white border-gray-150 hover:bg-slate-50 text-gray-600"
                    }`}
                  >
                    <div className="flex justify-between w-full items-start">
                      <span className="font-bold line-clamp-1 max-w-[70%]">{lote.LOTE_NOMBRE}</span>
                      <span className="text-[9px] bg-emerald-50 border border-emerald-200 text-emerald-800 px-1.5 py-0.5 rounded font-bold font-mono">
                        {cert?.NUMERO_CERTIFICADO || "CNE-OK"}
                      </span>
                    </div>
                    <span className="text-[10px] text-gray-400 mt-1">Estado: {lote.ESTADO_TRAMITE.replace(/_/g, " ")}</span>
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* Right column: Lot details and Certificate Generator */}
        <div className="lg:col-span-2 space-y-6">
          {selectedLote ? (
            <div className="bg-white rounded-xl shadow-sm border border-emerald-100 p-6 space-y-6">
              {/* Lot Info summary */}
              <div className="pb-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                  <h3 className="text-lg font-black text-gray-900">{selectedLote.LOTE_NOMBRE}</h3>
                  <p className="text-xs text-gray-500 mt-1">
                    Trámite ID: <strong className="font-mono text-gray-700">#{selectedLote.ID_LOTE}</strong> | Fecha de Creación: {selectedLote.FECHA_CREACIÓN}
                  </p>
                </div>
                <div className="inline-flex items-center gap-1 bg-purple-50 text-purple-800 border border-purple-200 px-3 py-1 rounded-full text-xs font-bold">
                  <ShieldCheck className="w-3.5 h-3.5" />
                  Listo para Verificación
                </div>
              </div>

              {/* Items Table Checklist */}
              <div>
                <h4 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Comprobación de Existencias Físicas</h4>
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse border border-gray-150 rounded-lg overflow-hidden">
                    <thead>
                      <tr className="bg-slate-50 text-gray-400 text-[10px] font-bold uppercase font-mono border-b border-gray-150">
                        <th className="py-2.5 px-3">Bien / Especificación Requerida</th>
                        <th className="py-2.5 px-3 text-center">Unidad</th>
                        <th className="py-2.5 px-3 text-right">Cant. Pedida</th>
                        <th className="py-2.5 px-3 text-center text-amber-700 bg-amber-50/50">Disponibilidad Almacén</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-xs">
                      {filteredItems.map((item) => (
                        <tr key={item.ID_MATRIZ_ITEM}>
                          <td className="py-3 px-3 font-semibold text-gray-800">
                            {item.DESCRIPCIÓN_BIEN}
                          </td>
                          <td className="py-3 px-3 text-center text-gray-500 font-medium">{item.UNIDAD_MEDIDA}</td>
                          <td className="py-3 px-3 text-right font-black text-gray-700">{item.CANTIDAD_REGULAR}</td>
                          <td className="py-3 px-3 text-center bg-red-50/30">
                            <span className="inline-flex items-center gap-1 text-[10px] text-red-700 font-bold">
                              <AlertTriangle className="w-3 h-3 text-red-500" />
                              0 unidades (Sin Stock)
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Certificate Generation Area */}
              {associatedCert ? (
                // View issued certificate
                <div className="bg-amber-50/50 border border-amber-300 rounded-xl p-6 relative overflow-hidden">
                  <div className="absolute right-4 top-4 opacity-5 pointer-events-none">
                    <FileCheck className="w-32 h-32 text-amber-900" />
                  </div>
                  <div className="flex items-center gap-2 mb-3">
                    <FileCheck className="w-5 h-5 text-amber-700 animate-pulse" />
                    <span className="text-xs font-black text-amber-800 uppercase tracking-wider">
                      CERTIFICADO DE NO EXISTENCIA EXPEDIDO
                    </span>
                  </div>
                  <h4 className="text-xl font-mono font-bold text-amber-950 border-b border-amber-200 pb-2">
                    Código: {associatedCert.NUMERO_CERTIFICADO}
                  </h4>
                  <div className="mt-4 space-y-3 text-xs text-amber-900">
                    <p className="italic bg-white p-3 rounded-lg border border-amber-100">
                      "{associatedCert.COMENTARIOS}"
                    </p>
                    <div className="flex justify-between text-[11px] font-mono pt-2 border-t border-dashed border-amber-200">
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3.5 h-3.5" /> Fecha: {new Date(associatedCert.FECHA_EMISIÓN).toLocaleString()}
                      </span>
                      <span className="flex items-center gap-1 font-bold">
                        <User className="w-3.5 h-3.5" /> Firmado por Almacén General
                      </span>
                    </div>
                  </div>
                </div>
              ) : (
                // Issue a certificate form
                <div className="bg-slate-50 border border-gray-200 rounded-xl p-5 space-y-4">
                  <div className="flex justify-between items-center">
                    <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                      <FileText className="w-4 h-4 text-emerald-600" />
                      Expedir Certificado de No Coexistencia / Inexistencia
                    </h4>
                    <button
                      id="btn-auto-fill-cert"
                      type="button"
                      onClick={triggerAutoCode}
                      className="text-[10px] bg-emerald-100 hover:bg-emerald-200 text-emerald-800 px-2 py-1 rounded font-bold transition-all"
                    >
                      Autocompletar Formato
                    </button>
                  </div>

                  <form id="form-generate-cert" onSubmit={handleGenerateCertificate} className="space-y-4">
                    <div>
                      <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                        Número de Certificado (Consecutivo Oficial)
                      </label>
                      <input
                        type="text"
                        required
                        placeholder="Ej: CNE-2026-0124"
                        value={certificadoNum}
                        onChange={(e) => setCertificadoNum(e.target.value)}
                        className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono font-bold focus:outline-emerald-500"
                      />
                    </div>

                    <div>
                      <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                        Comentarios y Justificación de Stock
                      </label>
                      <textarea
                        required
                        rows={3}
                        placeholder="Detalle el resultado de la búsqueda en inventario, justificando por qué no se cuenta con los bienes en la bodega del centro..."
                        value={comentariosAlmacen}
                        onChange={(e) => setComentariosAlmacen(e.target.value)}
                        className="w-full bg-white border border-gray-300 rounded-lg p-3 text-xs focus:outline-emerald-500 font-sans"
                      />
                    </div>

                    <button
                      id="btn-submit-cert"
                      type="submit"
                      className="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 rounded-lg text-xs transition-all shadow"
                    >
                      Firmar y Emitir Certificado de No Existencia
                    </button>
                  </form>
                </div>
              )}
            </div>
          ) : (
            <div className="bg-white border border-gray-150 rounded-xl p-12 text-center text-gray-400">
              <ShieldCheck className="h-12 w-12 text-gray-300 mx-auto mb-2" />
              <h3 className="text-base font-bold text-gray-700">No hay Lote Seleccionado</h3>
              <p className="text-xs text-gray-500 mt-1">
                Selecciona una de las solicitudes autorizadas de la barra lateral para validar inventarios y expedir certificados de viabilidad de compra.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
