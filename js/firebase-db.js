// firebase-db.js — Operaciones Firestore (reemplaza save_survey.php y get_pending_surveys.php)
import { db } from "./firebase-config.js";
import { getCurrentUser } from "./firebase-auth.js";
import {
  collection,
  doc,
  setDoc,
  getDoc,
  getDocs,
  query,
  orderBy,
  serverTimestamp
} from "https://www.gstatic.com/firebasejs/12.11.0/firebase-firestore.js";

const SURVEYS_COLLECTION = "surveys";

/**
 * Convierte el array [{contador, respuesta}, ...] a un mapa plano {"1": "1", "2": "0", ...}
 */
function buildRespuestasMap(arr) {
  const map = {};
  arr.forEach((r) => {
    map[String(r.contador)] = r.respuesta != null ? String(r.respuesta) : "";
  });
  return map;
}

/**
 * Guarda (crea o actualiza) una encuesta en Firestore.
 * Retorna { success, message, surveyId }
 */
export async function saveSurveyToFirestore(payload) {
  const user = getCurrentUser();
  if (!user) {
    return { success: false, message: "No hay sesión activa." };
  }

  if (!payload.empresa || payload.empresa.trim() === "") {
    return { success: false, message: "El nombre de la empresa es obligatorio." };
  }

  let surveyId = payload.surveyId || null;
  const isUpdate = !!surveyId;

  if (!surveyId) {
    const cleanName = payload.empresa
      .replace(/[^a-zA-Z0-9]/g, "")
      .substring(0, 5)
      .toUpperCase();
    const now = new Date();
    const dateStr =
      now.getFullYear().toString() +
      String(now.getMonth() + 1).padStart(2, "0") +
      String(now.getDate()).padStart(2, "0") +
      "_" +
      String(now.getHours()).padStart(2, "0") +
      String(now.getMinutes()).padStart(2, "0") +
      String(now.getSeconds()).padStart(2, "0");
    surveyId = "PRQ_" + dateStr + "_" + cleanName;
  }

  // Construir documento
  const docData = {
    SurveyID: surveyId,
    Timestamp: new Date().toISOString(),
    Status: payload.status || "pendiente",
    uid: user.uid,
    updatedBy: user.displayName || user.email,
    updatedAt: serverTimestamp(),

    // Sección 1 — Antecedentes
    Empresa: payload.empresa || "",
    descripcionEmpresa: payload.descripcionEmpresa || "",
    FechaEntrevista: payload.fechaEntrevista || "",
    numeroPoliza: payload.numeroPoliza || "",
    Certificaciones: payload.certificaciones || "",
    PrincipalesClientes: payload.principalesClientes || "",
    TipoMercancia: payload.tipoMercancia || "",
    riesgosViales: payload.riesgosViales || "",
    oportunidadesMejora: payload.oportunidadesMejora || "",

    // Sección 2 — Datos de organización
    estadosServicio: payload.estadosServicio || "",
    numConductores: payload.numConductores || "",
    numTalleres: payload.numTalleres || "",
    kmRecorridosMes: payload.kmRecorridosMes || "",
    viajesAnio: payload.viajesAnio || "",
    lesionadosMes: payload.lesionadosMes || "",
    estadoMatriz: payload.estadoMatriz || "",
    municipioMatriz: payload.municipioMatriz || "",
    UbicacionesAdicionales: payload.ubicacionesAdicionales || "",

    // Siniestralidad
    fechaConsultaSiniestralidad: payload.fechaConsultaSiniestralidad || "",
    frecuenciaSiniestralidad: payload.frecuenciaSiniestralidad || "",
    severidadSiniestralidad: payload.severidadSiniestralidad || "",
    tablaSiniestros: payload.tablaSiniestros || [],

    // Comentarios
    comentariosFinales: payload.comentarioFinales || "",

    // Secciones desactivadas
    SeccionesDesactivadas: (payload.disabledSections || []).join(","),

    // Entrevistados (objeto anidado)
    seccionesInfo: payload.seccionesInfo || {},

    // Respuestas: mapa simple { "1": "1", "2": "0", "35": "texto libre", ... }
    respuestas: buildRespuestasMap(payload.respuestasCuestionario || [])
  };

  try {
    const docRef = doc(db, SURVEYS_COLLECTION, surveyId);
    await setDoc(docRef, docData, { merge: isUpdate });
    return { success: true, message: "Guardado correctamente.", surveyId };
  } catch (error) {
    console.error("Error al guardar en Firestore:", error);
    return { success: false, message: "Error al guardar: " + error.message };
  }
}

/**
 * Carga todas las encuestas desde Firestore.
 * Retorna un array de objetos compatible con el formato anterior (CSV plano).
 */
export async function loadAllSurveys() {
  try {
    const q = query(
      collection(db, SURVEYS_COLLECTION),
      orderBy("Timestamp", "desc")
    );
    const snapshot = await getDocs(q);
    const surveys = [];

    snapshot.forEach((docSnap) => {
      const d = docSnap.data();

      // Convertir de vuelta a formato plano compatible con populateForm()
      const flat = {
        SurveyID: d.SurveyID || docSnap.id,
        Timestamp: d.Timestamp || "",
        Status: d.Status || "pendiente",
        Empresa: d.Empresa || "",
        descripcionEmpresa: d.descripcionEmpresa || "",
        FechaEntrevista: d.FechaEntrevista || "",
        numeroPoliza: d.numeroPoliza || "",
        Certificaciones: d.Certificaciones || "",
        PrincipalesClientes: d.PrincipalesClientes || "",
        TipoMercancia: d.TipoMercancia || "",
        riesgosViales: d.riesgosViales || "",
        oportunidadesMejora: d.oportunidadesMejora || "",
        estadosServicio: d.estadosServicio || "",
        numConductores: d.numConductores || "",
        numTalleres: d.numTalleres || "",
        kmRecorridosMes: d.kmRecorridosMes || "",
        viajesAnio: d.viajesAnio || "",
        lesionadosMes: d.lesionadosMes || "",
        estadoMatriz: d.estadoMatriz || "",
        municipioMatriz: d.municipioMatriz || "",
        UbicacionesAdicionales: d.UbicacionesAdicionales || "",
        fechaConsultaSiniestralidad: d.fechaConsultaSiniestralidad || "",
        frecuenciaSiniestralidad: d.frecuenciaSiniestralidad || "",
        severidadSiniestralidad: d.severidadSiniestralidad || "",
        tablaSiniestros: d.tablaSiniestros || [],
        comentarioFinales: d.comentariosFinales || "",
        SeccionesDesactivadas: d.SeccionesDesactivadas || ""
      };

      // Reconstruir claves de entrevistados para populateForm()
      if (d.seccionesInfo) {
        Object.keys(d.seccionesInfo).forEach((secName) => {
          const key = secName.replace(/ /g, "_").replace(/[^a-zA-Z0-9_]/g, "");
          flat["Entrevistado_" + key] = d.seccionesInfo[secName].entrevistado || "";
          flat["Puesto_" + key] = d.seccionesInfo[secName].puesto || "";
          flat["Antiguedad_" + key] = d.seccionesInfo[secName].antiguedad || "";
        });
      }

      // Reconstruir claves de preguntas para populateForm()
      if (d.respuestas && typeof d.respuestas === "object") {
        Object.keys(d.respuestas).forEach((contador) => {
          flat["Pregunta_" + contador] = d.respuestas[contador] || "";
        });
      }

      surveys.push(flat);
    });

    return surveys;
  } catch (error) {
    console.error("Error al cargar encuestas:", error);
    throw error;
  }
}
