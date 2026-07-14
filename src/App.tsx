import { Routes, Route } from 'react-router';
import HomePage from '@/pages/HomePage';
import AssessmentPage from '@/pages/AssessmentPage';
import ReportPage from '@/pages/ReportPage';
import AdminPage from '@/pages/AdminPage';

function App() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/assessment" element={<AssessmentPage />} />
      <Route path="/report" element={<ReportPage />} />
      <Route path="/report/:token" element={<ReportPage />} />
      <Route path="/admin" element={<AdminPage />} />
      <Route path="*" element={<HomePage />} />
    </Routes>
  );
}

export default App;
