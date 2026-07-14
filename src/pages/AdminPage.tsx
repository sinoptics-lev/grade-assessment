import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { apiRequest } from '@/hooks/useApi';
import { useAuth } from '@/hooks/useApi';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Shield, LogOut, Users, BarChart3, Activity, TrendingUp, Search, Eye, Trash2, RefreshCw, Loader2, Award, Brain, ChevronLeft } from 'lucide-react';
import type { DashboardStats, AssessmentListItem } from '@/types';
import { LEVEL_NAMES, LEVEL_COLORS } from '@/types';

export default function AdminPage() {
  const navigate = useNavigate();
  const { isAuthenticated, login, logout } = useAuth();
  const [loginData, setLoginData] = useState({ username: '', password: '' });
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [assessments, setAssessments] = useState<AssessmentListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [selectedAssessment, setSelectedAssessment] = useState<AssessmentListItem | null>(null);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoginLoading(true); setLoginError('');
    try {
      const response = await apiRequest('admin', 'POST', loginData, { path: 'login' });
      const resp = response as Record<string, unknown>;
      if (resp.success === true && resp.token) login(resp.token as string);
      else setLoginError('Ошибка');
    } catch (err) { setLoginError(err instanceof Error ? err.message : 'Ошибка'); }
    finally { setLoginLoading(false); }
  };

  useEffect(() => { if (isAuthenticated) loadDashboard(); }, [isAuthenticated]);

  const loadDashboard = async () => {
    setLoading(true);
    try {
      const [dashRes, assessRes] = await Promise.all([
        apiRequest('admin', 'GET', undefined, { path: 'dashboard' }),
        apiRequest('admin', 'GET', undefined, { path: 'assessments' })
      ]);
      const d = dashRes as Record<string, unknown>;
      const a = assessRes as Record<string, unknown>;
      if (d.success === true) setStats(d.stats as DashboardStats);
      if (a.success === true) setAssessments(a.assessments as AssessmentListItem[]);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Удалить?')) return;
    try { await apiRequest('admin', 'POST', { id }, { path: 'delete-assessment' }); setAssessments(assessments.filter(a => a.id !== id)); } catch { alert('Ошибка'); }
  };

  const filtered = assessments.filter(a => {
    const ms = !searchTerm || a.full_name.toLowerCase().includes(searchTerm.toLowerCase()) || a.position.toLowerCase().includes(searchTerm.toLowerCase());
    const mf = !statusFilter || a.status === statusFilter;
    return ms && mf;
  });

  if (!isAuthenticated) return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
      <Card className="max-w-md w-full mx-4 shadow-lg">
        <CardHeader className="text-center">
          <div className="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4"><Shield className="w-8 h-8 text-white" /></div>
          <CardTitle className="text-2xl">Админ-панель</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleLogin} className="space-y-4">
            <div><label className="text-sm font-medium mb-1 block">Логин</label>
              <Input placeholder="admin" value={loginData.username} onChange={e => setLoginData({ ...loginData, username: e.target.value })} required /></div>
            <div><label className="text-sm font-medium mb-1 block">Пароль</label>
              <Input type="password" placeholder="password" value={loginData.password} onChange={e => setLoginData({ ...loginData, password: e.target.value })} required /></div>
            {loginError && <div className="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{loginError}</div>}
            <Button type="submit" className="w-full" disabled={loginLoading}>
              {loginLoading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Shield className="w-4 h-4 mr-2" />}Войти
            </Button>
            <Button type="button" variant="ghost" className="w-full" onClick={() => navigate('/')}><ChevronLeft className="w-4 h-4 mr-2" />На главную</Button>
          </form>
        </CardContent>
      </Card></div>);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <header className="bg-white border-b shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3"><Shield className="w-6 h-6 text-primary" /><h1 className="text-xl font-bold">Админ-панель</h1></div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => navigate('/')}><ChevronLeft className="w-4 h-4 mr-2" />На сайт</Button>
            <Button variant="ghost" size="sm" onClick={logout}><LogOut className="w-4 h-4 mr-2" />Выйти</Button>
          </div></div></header>

      <div className="max-w-7xl mx-auto px-4 py-6">
        {loading ? <div className="flex items-center justify-center py-20"><Loader2 className="w-8 h-8 animate-spin text-primary" /></div> : (
          <>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
              <Card><CardContent className="pt-6"><div className="flex items-center justify-between"><div><p className="text-sm text-slate-600">Всего</p><p className="text-3xl font-bold">{stats?.total || 0}</p></div><Users className="w-8 h-8 text-blue-500" /></div></CardContent></Card>
              <Card><CardContent className="pt-6"><div className="flex items-center justify-between"><div><p className="text-sm text-slate-600">Завершено</p><p className="text-3xl font-bold">{stats?.completed || 0}</p></div><Award className="w-8 h-8 text-green-500" /></div></CardContent></Card>
              <Card><CardContent className="pt-6"><div className="flex items-center justify-between"><div><p className="text-sm text-slate-600">В процессе</p><p className="text-3xl font-bold">{stats?.in_progress || 0}</p></div><Activity className="w-8 h-8 text-orange-500" /></div></CardContent></Card>
              <Card><CardContent className="pt-6"><div className="flex items-center justify-between"><div><p className="text-sm text-slate-600">Средний балл</p><p className="text-3xl font-bold">{stats?.avg_score || 0}%</p></div><TrendingUp className="w-8 h-8 text-purple-500" /></div></CardContent></Card>
            </div>

            <Tabs defaultValue="assessments">
              <TabsList>
                <TabsTrigger value="assessments"><Users className="w-4 h-4 mr-2" />Оценки</TabsTrigger>
                <TabsTrigger value="analytics"><BarChart3 className="w-4 h-4 mr-2" />Аналитика</TabsTrigger>
              </TabsList>

              <TabsContent value="assessments" className="space-y-4">
                <div className="flex flex-wrap gap-3">
                  <div className="relative flex-1 min-w-[200px]"><Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <Input placeholder="Поиск..." className="pl-10" value={searchTerm} onChange={e => setSearchTerm(e.target.value)} /></div>
                  <select className="border rounded-md px-3 py-2 text-sm" value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
                    <option value="">Все</option><option value="completed">Завершено</option><option value="in_progress">В процессе</option>
                  </select>
                  <Button variant="outline" size="icon" onClick={loadDashboard}><RefreshCw className="w-4 h-4" /></Button>
                </div>
                <Card><CardContent className="p-0">
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead className="bg-slate-50 border-b"><tr>
                        <th className="text-left px-4 py-3 text-sm font-medium">ID</th><th className="text-left px-4 py-3 text-sm font-medium">Имя</th>
                        <th className="text-left px-4 py-3 text-sm font-medium">Позиция</th><th className="text-left px-4 py-3 text-sm font-medium">Статус</th>
                        <th className="text-left px-4 py-3 text-sm font-medium">Уровень</th><th className="text-left px-4 py-3 text-sm font-medium">Балл</th>
                        <th className="text-left px-4 py-3 text-sm font-medium">Дата</th><th className="text-left px-4 py-3 text-sm font-medium">Действия</th>
                      </tr></thead>
                      <tbody>
                        {filtered.map(a => <tr key={a.id} className="border-b hover:bg-slate-50">
                          <td className="px-4 py-3 text-sm">{a.id}</td><td className="px-4 py-3 font-medium">{a.full_name}</td>
                          <td className="px-4 py-3 text-sm">{a.position}</td>
                          <td className="px-4 py-3"><Badge variant={a.status === 'completed' ? 'default' : 'secondary'}>{a.status === 'completed' ? 'Завершено' : 'В процессе'}</Badge></td>
                          <td className="px-4 py-3">{a.final_level && <Badge variant="outline" className={LEVEL_COLORS[a.final_level]}>{LEVEL_NAMES[a.final_level]}</Badge>}</td>
                          <td className="px-4 py-3 text-sm">{a.max_possible_score > 0 ? <div className="flex items-center gap-2"><Progress value={(a.total_score / a.max_possible_score) * 100} className="w-16" /><span>{Math.round((a.total_score / a.max_possible_score) * 100)}%</span></div> : '-'}</td>
                          <td className="px-4 py-3 text-sm">{new Date(a.created_at).toLocaleDateString('ru-RU')}</td>
                          <td className="px-4 py-3"><div className="flex gap-1"><Button variant="ghost" size="icon" onClick={() => setSelectedAssessment(a)}><Eye className="w-4 h-4" /></Button><Button variant="ghost" size="icon" onClick={() => handleDelete(a.id)}><Trash2 className="w-4 h-4 text-red-500" /></Button></div></td>
                        </tr>)}
                      </tbody>
                    </table>
                  </div>
                  {filtered.length === 0 && <div className="text-center py-8 text-slate-500">Нет данных</div>}
                </CardContent></Card>
              </TabsContent>

              <TabsContent value="analytics">
                <Card><CardHeader><CardTitle className="flex items-center gap-2"><Brain className="w-5 h-5" />Статус API</CardTitle></CardHeader><CardContent>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between p-4 bg-green-50 rounded-lg"><div><p className="font-medium">Deepseek API</p><p className="text-sm text-slate-600">Добавьте ключ в .env</p></div><Badge variant="outline" className="bg-green-100 text-green-700">Готов</Badge></div>
                    <div className="flex items-center justify-between p-4 bg-blue-50 rounded-lg"><div><p className="font-medium">MySQL</p><p className="text-sm text-slate-600">Подключение настроено</p></div><Badge variant="outline" className="bg-blue-100 text-blue-700">Активно</Badge></div>
                  </div></CardContent></Card>
              </TabsContent>
            </Tabs>
          </>)}

        {selectedAssessment && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <Card className="max-w-2xl w-full max-h-[80vh] overflow-auto">
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Детали #{selectedAssessment.id}</CardTitle><Button variant="ghost" size="icon" onClick={() => setSelectedAssessment(null)}>✕</Button>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div><p className="text-sm text-slate-600">Имя</p><p className="font-medium">{selectedAssessment.full_name}</p></div>
                  <div><p className="text-sm text-slate-600">Позиция</p><p className="font-medium">{selectedAssessment.position}</p></div>
                  <div><p className="text-sm text-slate-600">Статус</p><Badge variant={selectedAssessment.status === 'completed' ? 'default' : 'secondary'}>{selectedAssessment.status}</Badge></div>
                  <div><p className="text-sm text-slate-600">Вопросов</p><p className="font-medium">{selectedAssessment.answered_count}</p></div>
                </div>
                {selectedAssessment.final_level && <Badge className={LEVEL_COLORS[selectedAssessment.final_level]}>{LEVEL_NAMES[selectedAssessment.final_level]}</Badge>}
                <Button className="w-full" onClick={() => { window.open(`/report`, '_blank'); }}><Eye className="w-4 h-4 mr-2" />Посмотреть отчёт</Button>
              </CardContent></Card></div>)}
      </div></div>);
}
