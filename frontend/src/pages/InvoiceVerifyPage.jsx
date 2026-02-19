import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { CheckCircle, XCircle, Loader2, Store, User, Calendar, DollarSign, FileText, ShieldCheck } from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/wp-json/petsgo/v1';

const InvoiceVerifyPage = () => {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const verify = async () => {
      try {
        const res = await fetch(`${API_URL}/invoice/validate/${token}`);
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.message || 'Boleta no encontrada');
        }
        setData(await res.json());
      } catch (e) {
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };
    verify();
  }, [token]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#00B8D9] mx-auto mb-4" />
          <p className="text-gray-500 text-lg">Verificando boleta…</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-red-50 px-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
          <XCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-800 mb-2">Boleta no válida</h1>
          <p className="text-gray-500 mb-6">{error}</p>
          <Link to="/" className="inline-block px-6 py-3 bg-[#00B8D9] text-white font-semibold rounded-xl hover:bg-[#009bb5] transition-colors no-underline">
            Ir al inicio
          </Link>
        </div>
      </div>
    );
  }

  const statusMap = {
    paid: { label: 'Pagado', color: 'text-green-600 bg-green-50 border-green-200' },
    pending: { label: 'Pendiente', color: 'text-yellow-600 bg-yellow-50 border-yellow-200' },
    shipped: { label: 'Enviado', color: 'text-blue-600 bg-blue-50 border-blue-200' },
    delivered: { label: 'Entregado', color: 'text-green-700 bg-green-50 border-green-300' },
    cancelled: { label: 'Cancelado', color: 'text-red-600 bg-red-50 border-red-200' },
  };

  const status = statusMap[data.order_status] || { label: data.order_status, color: 'text-gray-600 bg-gray-50 border-gray-200' };

  const formatDate = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-emerald-50 px-4 py-12">
      <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden">
        {/* Header */}
        <div className="bg-gradient-to-r from-[#00B8D9] to-[#00D9A5] px-6 py-8 text-center">
          <ShieldCheck className="w-16 h-16 text-white mx-auto mb-3 drop-shadow" />
          <h1 className="text-2xl font-bold text-white mb-1">Boleta Verificada</h1>
          <p className="text-white/80 text-sm">{data.message}</p>
        </div>

        {/* Body */}
        <div className="p-6 space-y-4">
          <div className="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
            <FileText className="w-5 h-5 text-[#00B8D9] flex-shrink-0" />
            <div>
              <p className="text-xs text-gray-400 uppercase tracking-wider">N° Boleta</p>
              <p className="text-lg font-bold text-gray-800">{data.invoice_number}</p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
              <Store className="w-5 h-5 text-[#00B8D9] flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wider">Tienda</p>
                <p className="font-semibold text-gray-800 text-sm">{data.store_name}</p>
                <p className="text-xs text-gray-400">RUT: {data.vendor_rut || '—'}</p>
              </div>
            </div>

            <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
              <User className="w-5 h-5 text-[#00B8D9] flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wider">Cliente</p>
                <p className="font-semibold text-gray-800 text-sm">{data.customer_name || '—'}</p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
              <DollarSign className="w-5 h-5 text-[#00B8D9] flex-shrink-0" />
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wider">Total</p>
                <p className="text-lg font-bold text-gray-800">${data.total?.toLocaleString('es-CL')}</p>
              </div>
            </div>

            <div className="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
              <Calendar className="w-5 h-5 text-[#00B8D9] flex-shrink-0" />
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wider">Emitida</p>
                <p className="font-semibold text-gray-800 text-sm">{formatDate(data.issued_at)}</p>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
            <span className="text-sm text-gray-500">Estado del pedido</span>
            <span className={`px-3 py-1 rounded-full text-sm font-semibold border ${status.color}`}>
              {status.label}
            </span>
          </div>

          <div className="flex items-center gap-2 text-green-600 bg-green-50 p-3 rounded-xl border border-green-200">
            <CheckCircle className="w-5 h-5 flex-shrink-0" />
            <span className="text-sm font-medium">Este documento es auténtico y fue emitido por PetsGo.</span>
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 pb-6">
          <Link to="/" className="block w-full text-center px-6 py-3 bg-[#00B8D9] text-white font-semibold rounded-xl hover:bg-[#009bb5] transition-colors no-underline">
            Ir a PetsGo
          </Link>
        </div>
      </div>
    </div>
  );
};

export default InvoiceVerifyPage;
